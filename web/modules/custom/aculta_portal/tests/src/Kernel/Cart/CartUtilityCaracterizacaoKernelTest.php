<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Cart;

use Drupal\aculta_portal\Cart\CartSite;
use Drupal\aculta_portal\Cart\CartUtility;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\CheckoutStepResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Caracteriza o agrupamento e os links do carrinho da barra (P1 do plano de ingressos).
 *
 * Mede o que o `CartUtility` entrega hoje: separadores por tipo de pedido, totais somados, `checkout_url` do grupo
 * só para assinatura, e os dois links globais (`/carrinho` e `/carrinho/finalizar`). Se o agrupamento ou os links
 * mudarem, o teste falha.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class CartUtilityCaracterizacaoKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout', 'domain', 'aculta_portal_cart_test'];

  /**
   * Conta compradora dos carrinhos.
   */
  private User $comprador;

  /**
   * Serviço em teste.
   */
  private CartUtility $cartUtility;

  /**
   * Gerenciador de domínios usado pelo serviço.
   */
  private DomainPurposeManager $purposeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->comprador = User::create([
      'name' => 'comprador',
      'mail' => 'comprador@example.test',
      'status' => 1,
    ]);
    $this->comprador->save();
    $this->setCurrentUser($this->comprador);

    Domain::create([
      'id' => 'aculta_org',
      'hostname' => 'principal.example.invalid',
      'name' => 'Principal',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ])->save();

    foreach (['default', 'curso', 'doacao', 'assinatura', 'ingresso', 'trilha'] as $tipo) {
      if (!OrderType::load($tipo)) {
        OrderType::create([
          'id' => $tipo,
          'label' => $tipo,
          'workflow' => 'order_default',
        ])->save();
      }
    }

    $this->purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );

    $this->cartUtility = $this->criarUtility(new CheckoutSettings($this->container->get('config.factory')));
  }

  /**
   * Instancia o serviço com os serviços reais do container.
   */
  private function criarUtility(CheckoutSettings $settings): CartUtility {
    return new CartUtility(
      $this->container->get('commerce_cart.cart_provider'),
      $this->purposeManager,
      $this->container->get('string_translation'),
      $settings,
      new CheckoutStepResolver($this->container->get('commerce_checkout.chain_checkout_flow_resolver')),
    );
  }

  /**
   * Cria um carrinho do tipo com um item de preço conhecido.
   */
  private function carrinho(string $tipo, string $preco): OrderInterface {
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
   * Índice por chave de separador.
   */
  private function separadores(array $build): array {
    $porChave = [];
    foreach ($build['sites'] as $site) {
      $porChave[$site['key']] = $site;
    }
    return $porChave;
  }

  /**
   * Sem carrinhos o painel sai vazio e sem total.
   */
  public function testCarrinhoVazio(): void {
    $build = $this->cartUtility->build();

    $this->assertTrue($build['empty']);
    $this->assertSame(0, $build['count']);
    $this->assertSame('', $build['total']);
    $this->assertSame([], $build['sites']);
  }

  /**
   * Cada tipo de pedido aparece no seu separador, com rótulo.
   */
  public function testAgrupamentoPorSeparador(): void {
    $this->carrinho('default', '10.00');
    $this->carrinho('curso', '20.00');
    $this->carrinho('doacao', '30.00');
    $this->carrinho('assinatura', '40.00');

    $build = $this->cartUtility->build();
    $porChave = $this->separadores($build);

    $this->assertSame([CartSite::ASSINATURA, CartSite::APOIO, CartSite::CURSOS, CartSite::LOJA], array_keys($porChave));
    $this->assertSame('Loja', $porChave[CartSite::LOJA]['label']);
    $this->assertSame('Cursos', $porChave[CartSite::CURSOS]['label']);
    $this->assertSame('Apoio', $porChave[CartSite::APOIO]['label']);
    $this->assertSame('Assinatura', $porChave[CartSite::ASSINATURA]['label']);
    $this->assertSame(1, $porChave[CartSite::LOJA]['count']);
    $this->assertSame(1, $porChave[CartSite::CURSOS]['count']);
    $this->assertSame(1, $porChave[CartSite::APOIO]['count']);
    $this->assertSame(1, $porChave[CartSite::ASSINATURA]['count']);
    // A assinatura tem checkout próprio, mas entra na contagem e no total do painel.
    $this->assertSame(4, $build['count']);
    $this->assertSame('100,00', $build['total']);
  }

  /**
   * Tipo sem separador próprio cai na Loja.
   */
  public function testTipoDesconhecidoCaiNaLoja(): void {
    $this->carrinho('ingresso', '10.00');

    $porChave = $this->separadores($this->cartUtility->build());

    $this->assertSame([CartSite::LOJA], array_keys($porChave));
    $this->assertSame(1, $porChave[CartSite::LOJA]['count']);
  }

  /**
   * O tipo de LMS configurado vai para Cursos; sem configuração, cai na Loja.
   */
  public function testTipoDeLmsConfiguradoVaiParaCursos(): void {
    $this->carrinho('trilha', '10.00');

    $semConfig = $this->separadores($this->cartUtility->build());
    $this->assertSame([CartSite::LOJA], array_keys($semConfig));

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn(string $chave) => match ($chave) {
      'lms_order_type' => 'trilha',
      'shop_order_type' => CheckoutSettings::DEFAULT_SHOP_ORDER_TYPE,
      default => NULL,
    });
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturnCallback(
      static fn(string $nome) => $nome === CheckoutSettings::CONFIG ? $config : NULL,
    );
    $this->cartUtility = $this->criarUtility(new CheckoutSettings($factory));

    $comConfig = $this->separadores($this->cartUtility->build());
    $this->assertSame([CartSite::CURSOS], array_keys($comConfig));
  }

  /**
   * Contagem e total somam todos os separadores.
   */
  public function testContagemETotalSomamTodosOsSeparadores(): void {
    $this->carrinho('default', '10.00');
    $this->carrinho('curso', '20.50');

    $build = $this->cartUtility->build();

    $this->assertFalse($build['empty']);
    $this->assertSame(2, $build['count']);
    $this->assertSame('30,50', $build['total']);
  }

  /**
   * Só o separador de assinatura traz link de checkout por grupo.
   */
  public function testApenasAssinaturaTemCheckoutPorGrupo(): void {
    $assinatura = $this->carrinho('assinatura', '40.00');
    $this->carrinho('default', '10.00');
    $this->carrinho('doacao', '30.00');

    $porChave = $this->separadores($this->cartUtility->build());

    $this->assertSame('', $porChave[CartSite::LOJA]['checkout_url']);
    $this->assertSame('', $porChave[CartSite::APOIO]['checkout_url']);
    $this->assertNotSame('', $porChave[CartSite::ASSINATURA]['checkout_url']);
    $this->assertStringContainsString('/checkout/' . $assinatura->id(), $porChave[CartSite::ASSINATURA]['checkout_url']);
  }

  /**
   * Os dois links globais: `/carrinho` e `/carrinho/finalizar` no domínio principal.
   */
  public function testLinksGlobais(): void {
    $this->carrinho('default', '10.00');

    $build = $this->cartUtility->build();

    $this->assertStringContainsString('principal.example.invalid', $build['url']);
    $this->assertStringEndsWith('/carrinho', $build['url']);
    $this->assertStringContainsString('principal.example.invalid', $build['checkout_url']);
    $this->assertStringEndsWith('/carrinho/finalizar', $build['checkout_url']);
  }

  /**
   * Itens do mesmo separador são listados do mais recente para o mais antigo.
   */
  public function testItensDoSeparadorDoMaisRecenteParaOMaisAntigo(): void {
    $pedido = $this->container->get('commerce_cart.cart_provider')
      ->createCart('default', $this->store, $this->comprador);
    $ids = [];
    foreach (['10.00', '20.00'] as $preco) {
      $item = OrderItem::create([
        'type' => 'test',
        'quantity' => 1,
        'unit_price' => new Price($preco, 'USD'),
      ]);
      $item->save();
      $ids[] = (int) $item->id();
      $pedido->addItem($item);
    }
    $pedido->save();

    $porChave = $this->separadores($this->cartUtility->build());
    $titulos = array_column($porChave[CartSite::LOJA]['items'], 'total');

    $this->assertSame(2, $porChave[CartSite::LOJA]['count']);
    $this->assertSame('30,00', $porChave[CartSite::LOJA]['total']);
    $this->assertSame(['20,00', '10,00'], $titulos);
  }

}
