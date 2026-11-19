<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutConfigurationResolver;
use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutContextRegistry;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\CheckoutPolicyManager;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contratos centrais de orquestração de checkout do Portal (P-CART B).
 */
#[Group('aculta_portal')]
final class CheckoutOrchestrationTest extends UnitTestCase {

  /**
   * Cria um provider de contexto de checkout para os testes.
   *
   * @param string $id
   *   Identificador do contexto.
   * @param int $weight
   *   Peso de ordenação do provider.
   * @param \Drupal\aculta_portal\Checkout\CheckoutMode $mode
   *   Modo de checkout admitido.
   * @param array $orderTypes
   *   Tipos de pedido associados ao contexto.
   * @param array $flows
   *   Fluxos de checkout associados ao contexto.
   *
   * @return \Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface
   *   Instância anônima do provider.
   */
  private function provider(string $id, int $weight, CheckoutMode $mode, array $orderTypes = [], array $flows = []): CheckoutContextProviderInterface {
    return new class($id, $weight, new CheckoutContextConfiguration($id, $mode, $orderTypes, $flows)) implements CheckoutContextProviderInterface {

      public function __construct(
        private readonly string $id,
        private readonly int $weight,
        private readonly CheckoutContextConfiguration $configuration,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return $this->id;
      }

      /**
       * {@inheritdoc}
       */
      public function label(): string {
        return $this->id;
      }

      /**
       * {@inheritdoc}
       */
      public function weight(): int {
        return $this->weight;
      }

      /**
       * {@inheritdoc}
       */
      public function configuration(): CheckoutContextConfiguration {
        return $this->configuration;
      }

    };
  }

  /**
   * Cria um gerenciador de políticas de checkout para os testes.
   *
   * @param array $providers
   *   Providers de contexto usados no registro.
   * @param array $settings
   *   Configurações de checkout aplicadas.
   *
   * @return \Drupal\aculta_portal\Checkout\CheckoutPolicyManager
   *   Gerenciador de políticas configurado.
   */
  private function policy(array $providers, array $settings = []): CheckoutPolicyManager {
    return new CheckoutPolicyManager(
      new CheckoutConfigurationResolver(new CheckoutContextRegistry($providers)),
      new CheckoutSettings($this->getConfigFactoryStub([CheckoutSettings::CONFIG => $settings])),
    );
  }

  /**
   * Verifica quais modalidades cada modo de checkout permite.
   *
   * @param \Drupal\aculta_portal\Checkout\CheckoutMode $modo
   *   Modo de checkout avaliado.
   * @param bool $dedicado
   *   Indica se o checkout dedicado é esperado.
   * @param bool $comum
   *   Indica se o checkout comum é esperado.
   */
  #[DataProvider('modos')]
  public function testModoDeterminaDisponibilidade(CheckoutMode $modo, bool $dedicado, bool $comum): void {
    $this->assertSame($dedicado, $modo->allowsDedicated());
    $this->assertSame($comum, $modo->allowsCommon());
  }

  /**
   * Fornece os modos de checkout e as modalidades esperadas.
   *
   * @return array
   *   Conjunto de casos de teste do data provider.
   */
  public static function modos(): array {
    return [
      'dedicated' => [CheckoutMode::Dedicated, TRUE, FALSE],
      'common' => [CheckoutMode::Common, FALSE, TRUE],
      'both' => [CheckoutMode::Both, TRUE, TRUE],
    ];
  }

  /**
   * Confirma que a configuração normaliza e ordena os identificadores.
   */
  public function testConfiguracaoNormalizaIdsDeFormaDeterministica(): void {
    $a = new CheckoutContextConfiguration('x', CheckoutMode::Both, ['b', 'a', 'b', ''], ['z', 'y']);
    $b = new CheckoutContextConfiguration('x', CheckoutMode::Both, ['a', 'b'], ['y', 'z', 'y']);
    $this->assertSame(['a', 'b'], $a->orderTypeIds);
    $this->assertSame(['y', 'z'], $a->checkoutFlowIds);
    $this->assertEquals($a, $b);
  }

  /**
   * Confirma que o registro ordena por peso e depois por identificador.
   */
  public function testRegistryOrdenaPorPesoEDepoisPorId(): void {
    $registry = new CheckoutContextRegistry([
      $this->provider('lms', 20, CheckoutMode::Common),
      $this->provider('shop', 10, CheckoutMode::Common),
      $this->provider('donation', 20, CheckoutMode::Both),
    ]);
    $this->assertSame(['shop', 'donation', 'lms'], array_keys($registry->all()));
    $this->assertTrue($registry->has('lms'));
    $this->assertFalse($registry->has('outro'));
    $this->assertNull($registry->get('outro'));
  }

  /**
   * Confirma que o registro recusa identificadores duplicados.
   */
  public function testRegistryRecusaIdDuplicado(): void {
    $registry = new CheckoutContextRegistry([
      $this->provider('shop', 10, CheckoutMode::Common),
      $this->provider('shop', 20, CheckoutMode::Both),
    ]);
    $this->expectException(\LogicException::class);
    $registry->all();
  }

  /**
   * Confirma que o registro sem providers permanece vazio.
   */
  public function testRegistrySemProvidersFicaVazio(): void {
    $registry = new CheckoutContextRegistry([]);
    $this->assertSame([], $registry->all());
  }

  /**
   * Confirma que o resolvedor devolve a configuração do provider ou nulo.
   */
  public function testResolverDevolveAConfiguracaoDoProviderOuNull(): void {
    $resolver = new CheckoutConfigurationResolver(new CheckoutContextRegistry([
      $this->provider('donation', 10, CheckoutMode::Dedicated, ['doacao'], ['donation']),
    ]));
    $this->assertSame(CheckoutMode::Dedicated, $resolver->resolve('donation')->mode);
    $this->assertNull($resolver->resolve('inexistente'));
    $this->assertSame(['donation'], array_keys($resolver->resolveAll()));
  }

  /**
   * Confirma a política de disponibilidade por contexto de checkout.
   */
  public function testPoliticaDeDisponibilidadePorContexto(): void {
    $policy = $this->policy([
      $this->provider('donation', 10, CheckoutMode::Dedicated),
      $this->provider('shop', 20, CheckoutMode::Common),
      $this->provider('lms', 30, CheckoutMode::Both),
    ]);
    $this->assertTrue($policy->allowsDedicated('donation'));
    $this->assertFalse($policy->allowsCommon('donation'));
    $this->assertFalse($policy->allowsDedicated('shop'));
    $this->assertTrue($policy->allowsCommon('shop'));
    $this->assertTrue($policy->allowsDedicated('lms'));
    $this->assertTrue($policy->allowsCommon('lms'));
  }

  /**
   * Confirma que um contexto desconhecido nunca libera o checkout.
   */
  public function testContextoDesconhecidoNuncaLiberaCheckout(): void {
    $policy = $this->policy([]);
    $this->assertFalse($policy->allowsDedicated('qualquer'));
    $this->assertFalse($policy->allowsCommon('qualquer'));
  }

  /**
   * Confirma a detecção de conflito de tipo de pedido entre contextos.
   */
  public function testConflitoDeTipoDePedidoEntreContextos(): void {
    $policy = $this->policy([
      $this->provider('shop', 10, CheckoutMode::Common, ['default', 'curso']),
      $this->provider('lms', 20, CheckoutMode::Common, ['curso']),
      $this->provider('donation', 30, CheckoutMode::Both, ['doacao']),
    ]);
    $this->assertSame(['curso' => ['lms', 'shop']], $policy->orderTypeConflicts());
  }

  /**
   * Confirma que não há conflito quando cada tipo tem um único dono.
   */
  public function testSemConflitoQuandoCadaTipoTemUmDono(): void {
    $policy = $this->policy([
      $this->provider('shop', 10, CheckoutMode::Common, ['default']),
      $this->provider('lms', 20, CheckoutMode::Common, ['curso']),
    ]);
    $this->assertSame([], $policy->orderTypeConflicts());
  }

  /**
   * Confirma que as rotas globais fecham só quando o contexto controlador não admite o comum.
   */
  public function testRotasGlobaisFechamSoQuandoContextoControladorNaoAdmiteComum(): void {
    $gate = static fn(string $id, CheckoutMode $modo): CheckoutContextProviderInterface => new class($id, $modo) implements CheckoutContextProviderInterface {

      public function __construct(private readonly string $id, private readonly CheckoutMode $mode) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return $this->id;
      }

      /**
       * {@inheritdoc}
       */
      public function label(): string {
        return $this->id;
      }

      /**
       * {@inheritdoc}
       */
      public function weight(): int {
        return 0;
      }

      /**
       * {@inheritdoc}
       */
      public function configuration(): CheckoutContextConfiguration {
        return new CheckoutContextConfiguration($this->id, $this->mode, [], [], TRUE);
      }

    };
    $this->assertFalse($this->policy([$gate('donation', CheckoutMode::Dedicated)])->commonCheckoutRoutesAvailable());
    $this->assertTrue($this->policy([$gate('donation', CheckoutMode::Common)])->commonCheckoutRoutesAvailable());
    $this->assertTrue($this->policy([$gate('donation', CheckoutMode::Both)])->commonCheckoutRoutesAvailable());
  }

  /**
   * Confirma que um contexto sem gate não fecha as rotas globais.
   */
  public function testContextoSemGateNaoFechaAsRotasGlobais(): void {
    $this->assertTrue($this->policy([$this->provider('lms', 1, CheckoutMode::Dedicated)])->commonCheckoutRoutesAvailable());
  }

  /**
   * Confirma que sem providers as rotas globais ficam disponíveis.
   */
  public function testSemProvidersAsRotasGlobaisFicamDisponiveis(): void {
    $this->assertTrue($this->policy([])->commonCheckoutRoutesAvailable());
  }

  /**
   * Confirma que a chave global desligada fecha as rotas e o checkout comum de todos os contextos.
   */
  public function testChaveGlobalDesligadaFechaAsRotasEOCheckoutComumDeTodosOsContextos(): void {
    $providers = [$this->provider('lms', 1, CheckoutMode::Both), $this->provider('shop', 2, CheckoutMode::Common)];
    $policy = $this->policy($providers, ['common_checkout_enabled' => FALSE]);
    $this->assertFalse($policy->commonCheckoutRoutesAvailable());
    $this->assertFalse($policy->allowsCommon('lms'));
    $this->assertFalse($policy->allowsCommon('shop'));
    // O checkout dedicado não depende da chave do comum.
    $this->assertTrue($policy->allowsDedicated('lms'));
  }

  /**
   * Confirma que a chave global ausente ou ligada mantém o comportamento anterior.
   */
  public function testChaveGlobalAusenteOuLigadaMantemOComportamentoAnterior(): void {
    $providers = [$this->provider('shop', 1, CheckoutMode::Common)];
    foreach ([[], ['common_checkout_enabled' => TRUE]] as $settings) {
      $policy = $this->policy($providers, $settings);
      $this->assertTrue($policy->commonCheckoutRoutesAvailable());
      $this->assertTrue($policy->allowsCommon('shop'));
    }
  }

}
