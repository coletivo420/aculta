<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutConfigurationResolver;
use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutContextRegistry;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\CheckoutPolicyManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contratos centrais de orquestração de checkout do Portal (P-CART B).
 */
#[Group('aculta_portal')]
final class CheckoutOrchestrationTest extends UnitTestCase {

  private function provider(string $id, int $weight, CheckoutMode $mode, array $orderTypes = [], array $flows = []): CheckoutContextProviderInterface {
    return new class($id, $weight, new CheckoutContextConfiguration($id, $mode, $orderTypes, $flows)) implements CheckoutContextProviderInterface {

      public function __construct(
        private readonly string $id,
        private readonly int $weight,
        private readonly CheckoutContextConfiguration $configuration,
      ) {}

      public function id(): string {
        return $this->id;
      }

      public function label(): string {
        return $this->id;
      }

      public function weight(): int {
        return $this->weight;
      }

      public function configuration(): CheckoutContextConfiguration {
        return $this->configuration;
      }

    };
  }

  private function policy(array $providers): CheckoutPolicyManager {
    return new CheckoutPolicyManager(new CheckoutConfigurationResolver(new CheckoutContextRegistry($providers)));
  }

  #[DataProvider('modos')]
  public function testModoDeterminaDisponibilidade(CheckoutMode $modo, bool $dedicado, bool $comum): void {
    $this->assertSame($dedicado, $modo->allowsDedicated());
    $this->assertSame($comum, $modo->allowsCommon());
  }

  public static function modos(): array {
    return [
      'dedicated' => [CheckoutMode::Dedicated, TRUE, FALSE],
      'common' => [CheckoutMode::Common, FALSE, TRUE],
      'both' => [CheckoutMode::Both, TRUE, TRUE],
    ];
  }

  public function testConfiguracaoNormalizaIdsDeFormaDeterministica(): void {
    $a = new CheckoutContextConfiguration('x', CheckoutMode::Both, ['b', 'a', 'b', ''], ['z', 'y']);
    $b = new CheckoutContextConfiguration('x', CheckoutMode::Both, ['a', 'b'], ['y', 'z', 'y']);
    $this->assertSame(['a', 'b'], $a->orderTypeIds);
    $this->assertSame(['y', 'z'], $a->checkoutFlowIds);
    $this->assertEquals($a, $b);
  }

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

  public function testRegistryRecusaIdDuplicado(): void {
    $registry = new CheckoutContextRegistry([
      $this->provider('shop', 10, CheckoutMode::Common),
      $this->provider('shop', 20, CheckoutMode::Both),
    ]);
    $this->expectException(\LogicException::class);
    $registry->all();
  }

  public function testRegistrySemProvidersFicaVazio(): void {
    $registry = new CheckoutContextRegistry([]);
    $this->assertSame([], $registry->all());
  }

  public function testResolverDevolveAConfiguracaoDoProviderOuNull(): void {
    $resolver = new CheckoutConfigurationResolver(new CheckoutContextRegistry([
      $this->provider('donation', 10, CheckoutMode::Dedicated, ['doacao'], ['donation']),
    ]));
    $this->assertSame(CheckoutMode::Dedicated, $resolver->resolve('donation')->mode);
    $this->assertNull($resolver->resolve('inexistente'));
    $this->assertSame(['donation'], array_keys($resolver->resolveAll()));
  }

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

  public function testContextoDesconhecidoNuncaLiberaCheckout(): void {
    $policy = $this->policy([]);
    $this->assertFalse($policy->allowsDedicated('qualquer'));
    $this->assertFalse($policy->allowsCommon('qualquer'));
  }

  public function testConflitoDeTipoDePedidoEntreContextos(): void {
    $policy = $this->policy([
      $this->provider('shop', 10, CheckoutMode::Common, ['default', 'curso']),
      $this->provider('lms', 20, CheckoutMode::Common, ['curso']),
      $this->provider('donation', 30, CheckoutMode::Both, ['doacao']),
    ]);
    $this->assertSame(['curso' => ['lms', 'shop']], $policy->orderTypeConflicts());
  }

  public function testSemConflitoQuandoCadaTipoTemUmDono(): void {
    $policy = $this->policy([
      $this->provider('shop', 10, CheckoutMode::Common, ['default']),
      $this->provider('lms', 20, CheckoutMode::Common, ['curso']),
    ]);
    $this->assertSame([], $policy->orderTypeConflicts());
  }

}
