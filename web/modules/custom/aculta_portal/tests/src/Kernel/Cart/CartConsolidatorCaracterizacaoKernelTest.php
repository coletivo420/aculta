<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Cart;

use Drupal\aculta_portal\Cart\CartConsolidator;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Caracteriza a fusão do carrinho integrado (P1 do plano de ingressos).
 *
 * Registra o comportamento atual do `CartConsolidator`: um pedido só por carrinho, fusão dos itens no pedido da
 * categoria mais exigente e precedência LMS > Loja > Doações. Nenhuma asserção aqui define comportamento novo; se a
 * fusão ou a precedência mudarem, o teste falha.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class CartConsolidatorCaracterizacaoKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout'];

  /**
   * Conta compradora dos carrinhos.
   */
  private User $comprador;

  /**
   * Sessão de carrinho observada pelo teste.
   *
   * O Kernel não tem sessão HTTP real; o mock registra a chamada que o serviço
   * de produção faria.
   */
  private CartSessionInterface $cartSession;

  /**
   * Último ID de carrinho marcado como ativo.
   */
  private ?int $carrinhoAtivo = NULL;

  /**
   * Serviço em teste.
   */
  private CartConsolidator $consolidador;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->comprador = User::create([
      'name' => 'comprador',
      'mail' => 'comprador@example.test',
      'status' => 1,
    ]);
    $this->comprador->save();
    $this->setCurrentUser($this->comprador);

    // Tipos de pedido reais do site, mais um tipo que não pertence ao carrinho
    // integrado (candidato a Eventos na P2) e um tipo de LMS alternativo.
    foreach (['default', 'curso', 'doacao', 'assinatura', 'ingresso', 'trilha'] as $tipo) {
      if (!OrderType::load($tipo)) {
        OrderType::create([
          'id' => $tipo,
          'label' => $tipo,
          'workflow' => 'order_default',
        ])->save();
      }
    }

    $this->cartSession = $this->createMock(CartSessionInterface::class);
    $this->cartSession->method('addCartId')->willReturnCallback(function (int|string $cart_id): void {
      $this->carrinhoAtivo = (int) $cart_id;
    });

    $this->consolidador = $this->criarConsolidador();
  }

  /**
   * Instancia o serviço com os serviços reais do container.
   */
  private function criarConsolidador(?CheckoutSettings $settings = NULL): CartConsolidator {
    return new CartConsolidator(
      $this->container->get('commerce_cart.cart_provider'),
      $this->cartSession,
      $this->container->get('commerce_store.current_store'),
      $this->container->get('current_user'),
      $settings ?? new CheckoutSettings($this->container->get('config.factory')),
    );
  }

  /**
   * Cria um carrinho do tipo com um item de preço conhecido.
   */
  private function carrinho(string $tipo, string $preco = '10.00'): OrderInterface {
    $pedido = $this->container->get('commerce_cart.cart_provider')
      ->createCart($tipo, $this->store, $this->comprador);
    $item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price($preco, 'USD'),
    ]);
    $item->save();
    $pedido->addItem($item);
    $pedido->save();
    return $pedido;
  }

  /**
   * Configura o tipo de pedido do LMS no `CheckoutSettings`.
   *
   * O módulo de configuração do Portal não está habilitado neste Kernel, então
   * a leitura é servida por um stub com o mesmo contrato do serviço real.
   */
  private function configurarLms(string $tipo): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn(string $chave) => match ($chave) {
      'lms_order_type' => $tipo,
      'shop_order_type' => CheckoutSettings::DEFAULT_SHOP_ORDER_TYPE,
      default => NULL,
    });
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturnCallback(
      static fn(string $nome) => $nome === CheckoutSettings::CONFIG ? $config : NULL,
    );
    $this->consolidador = $this->criarConsolidador(new CheckoutSettings($factory));
  }

  /**
   * Sem carrinhos, nada é consolidado.
   */
  public function testSemCarrinhosRetornaNull(): void {
    $this->assertNull($this->consolidador->consolidar());
  }

  /**
   * Um carrinho só é devolvido como está e vira o carrinho ativo.
   */
  public function testCarrinhoUnicoNaoEAparece(): void {
    $doacao = $this->carrinho('doacao');
    $item_id = (int) $doacao->getItems()[0]->id();

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame((int) $doacao->id(), (int) $destino->id());
    $this->assertSame('doacao', $destino->bundle());
    $this->assertSame([$item_id], array_map(static fn($i): int => (int) $i->id(), $destino->getItems()));
    $this->assertSame((int) $destino->id(), $this->carrinhoAtivo);
  }

  /**
   * LMS (curso) vence Loja e Doações: o pedido de curso recebe todos os itens.
   */
  public function testPrecedenciaCursoSobreLojaEDoacao(): void {
    $doacao = $this->carrinho('doacao');
    $loja = $this->carrinho('default');
    $curso = $this->carrinho('curso');

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame((int) $curso->id(), (int) $destino->id());
    $this->assertSame('curso', $destino->bundle());
    $this->assertCount(3, $destino->getItems());
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $doacao->id()));
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $loja->id()));
  }

  /**
   * Sem curso, a Loja vence a Doação.
   */
  public function testPrecedenciaLojaSobreDoacao(): void {
    $doacao = $this->carrinho('doacao');
    $loja = $this->carrinho('default');

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame((int) $loja->id(), (int) $destino->id());
    $this->assertSame('default', $destino->bundle());
    $this->assertCount(2, $destino->getItems());
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $doacao->id()));
  }

  /**
   * Só Doações: o pedido de doação permanece doação.
   */
  public function testApenasDoacaoMantemOTipoDeDoacao(): void {
    $doacao = $this->carrinho('doacao');

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame((int) $doacao->id(), (int) $destino->id());
    $this->assertSame('doacao', $destino->bundle());
  }

  /**
   * A assinatura fica fora da fusão e não é apagada.
   */
  public function testAssinaturaFicaForaDaFusao(): void {
    $assinatura = $this->carrinho('assinatura');
    $loja = $this->carrinho('default');

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame((int) $loja->id(), (int) $destino->id());
    $this->assertSame('default', $destino->bundle());
    $this->assertCount(1, $destino->getItems());
    $remanescente = $this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $assinatura->id());
    $this->assertNotNull($remanescente);
    $this->assertSame('assinatura', $remanescente->bundle());
  }

  /**
   * Tipo fora da precedência cai no tipo da Loja e seus carrinhos são apagados.
   *
   * É o caso que a P2 terá de tratar ao incluir Eventos: hoje um pedido de tipo desconhecido não sobrevive à fusão
   * com um carrinho da Loja nem sozinho.
   */
  public function testTipoForaDaPrecedenciaCaiNaLoja(): void {
    $ingresso = $this->carrinho('ingresso');

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame('default', $destino->bundle());
    $this->assertNotSame((int) $ingresso->id(), (int) $destino->id());
    $this->assertCount(1, $destino->getItems());
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $ingresso->id()));
  }

  /**
   * O tipo de LMS configurado tem precedência sobre o histórico `curso`.
   */
  public function testLmsConfiguradoVemAntesDoCursoHistorico(): void {
    $this->configurarLms('trilha');
    $curso = $this->carrinho('curso');
    $trilha = $this->carrinho('trilha');

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $this->assertSame((int) $trilha->id(), (int) $destino->id());
    $this->assertSame('trilha', $destino->bundle());
    $this->assertCount(2, $destino->getItems());
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $curso->id()));
  }

  /**
   * Os itens movidos apontam para o pedido de destino e o pedido antigo some.
   */
  public function testItensMovidosApontamParaODestino(): void {
    $loja = $this->carrinho('default');
    $curso = $this->carrinho('curso');
    $item_loja = (int) $loja->getItems()[0]->id();

    $destino = $this->consolidador->consolidar();

    $this->assertNotNull($destino);
    $item = $this->container->get('entity_type.manager')->getStorage('commerce_order_item')->load($item_loja);
    $this->assertSame((int) $destino->id(), (int) $item->get('order_id')->target_id);
  }

  /**
   * A consolidação é idempotente: repetir não altera o pedido nem cria outro.
   */
  public function testConsolidarDuasVezesNaoCriaOutroPedido(): void {
    $loja = $this->carrinho('default');
    $curso = $this->carrinho('curso');

    $primeiro = $this->consolidador->consolidar();
    $segundo = $this->consolidador->consolidar();

    $this->assertNotNull($primeiro);
    $this->assertNotNull($segundo);
    $this->assertSame((int) $primeiro->id(), (int) $segundo->id());
    $this->assertSame((int) $curso->id(), (int) $segundo->id());
    $this->assertCount(2, $segundo->getItems());
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $loja->id()));
  }

}
