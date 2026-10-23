<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutConfigurationResolver;
use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutContextRegistry;
use Drupal\aculta_portal\Checkout\CheckoutMappingValidator;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Conflito de tipo de pedido ao editar a aba Loja ou LMS (P-CART E).
 */
#[Group('aculta_portal')]
final class CheckoutMappingValidatorTest extends UnitTestCase {

  private function validator(array $claims): CheckoutMappingValidator {
    $providers = [];
    foreach ($claims as $id => $types) {
      $providers[] = new class($id, $types) implements CheckoutContextProviderInterface {

        public function __construct(private readonly string $id, private readonly array $types) {}

        public function id(): string {
          return $this->id;
        }

        public function label(): string {
          return $this->id;
        }

        public function weight(): int {
          return 0;
        }

        public function configuration(): CheckoutContextConfiguration {
          return new CheckoutContextConfiguration($this->id, CheckoutMode::Common, $this->types);
        }

      };
    }
    return new CheckoutMappingValidator(new CheckoutConfigurationResolver(new CheckoutContextRegistry($providers)));
  }

  public function testTipoUsadoPorOutroContextoEConflito(): void {
    $validator = $this->validator(['shop' => ['default'], 'lms' => ['curso'], 'donation' => ['doacao', 'assinatura']]);
    $this->assertSame(['donation'], $validator->conflictingContexts('lms', 'doacao'));
    $this->assertSame(['lms'], $validator->conflictingContexts('shop', 'curso'));
  }

  public function testOProprioContextoNaoConflitaConsigoMesmo(): void {
    $validator = $this->validator(['shop' => ['default'], 'lms' => ['curso']]);
    $this->assertSame([], $validator->conflictingContexts('shop', 'default'));
    $this->assertSame([], $validator->conflictingContexts('lms', 'aula'));
  }

}
