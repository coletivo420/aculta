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

  /**
   * Cria um validador de mapeamento com os contextos informados.
   *
   * @param array $claims
   *   Mapa de identificador do contexto para os tipos de pedido reivindicados.
   *
   * @return \Drupal\aculta_portal\Checkout\CheckoutMappingValidator
   *   Validador configurado para os testes.
   */
  private function validator(array $claims): CheckoutMappingValidator {
    $providers = [];
    foreach ($claims as $id => $types) {
      $providers[] = new class($id, $types) implements CheckoutContextProviderInterface {

        public function __construct(private readonly string $id, private readonly array $types) {}

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
          return new CheckoutContextConfiguration($this->id, CheckoutMode::Common, $this->types);
        }

      };
    }
    return new CheckoutMappingValidator(new CheckoutConfigurationResolver(new CheckoutContextRegistry($providers)));
  }

  /**
   * Confirma que um tipo usado por outro contexto é reportado como conflito.
   */
  public function testTipoUsadoPorOutroContextoEConflito(): void {
    $validator = $this->validator(['shop' => ['default'], 'lms' => ['curso'], 'donation' => ['doacao', 'assinatura']]);
    $this->assertSame(['donation'], $validator->conflictingContexts('lms', 'doacao'));
    $this->assertSame(['lms'], $validator->conflictingContexts('shop', 'curso'));
  }

  /**
   * Confirma que o próprio contexto não conflita consigo mesmo.
   */
  public function testOProprioContextoNaoConflitaConsigoMesmo(): void {
    $validator = $this->validator(['shop' => ['default'], 'lms' => ['curso']]);
    $this->assertSame([], $validator->conflictingContexts('shop', 'default'));
    $this->assertSame([], $validator->conflictingContexts('lms', 'aula'));
  }

}
