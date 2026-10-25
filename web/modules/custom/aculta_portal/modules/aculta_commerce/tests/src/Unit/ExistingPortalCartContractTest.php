<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_portal\Cart\CartSite;
use Drupal\aculta_portal\Checkout\CheckoutSlugMap;
use Drupal\Tests\UnitTestCase;

/**
 * Snapshot contract for the existing public cart; does not change runtime.
 *
 * @group aculta_commerce
 */
final class ExistingPortalCartContractTest extends UnitTestCase {

  /**
   * The portal's user-visible cart separators are compatibility contracts.
   *
   * @dataProvider orderTypeContextProvider
   */
  public function testExistingOrderTypeLabels(string $type, string $site, string $label): void {
    self::assertSame($site, CartSite::paraTipo($type));
    self::assertSame($label, CartSite::rotulo($site));
  }

  /**
   * Existing order types and fallback should not change unnoticed.
   *
   * @return array<string, array{string, string, string}>
   *   Existing order type, cart separator, and visible label.
   */
  public static function orderTypeContextProvider(): array {
    return [
      'store' => ['default', 'loja', 'Loja'],
      'lms' => ['curso', 'cursos', 'Cursos'],
      'donation' => ['doacao', 'apoio', 'Apoio'],
      'subscription' => ['assinatura', 'assinatura', 'Assinatura'],
      'unknown type fallback' => ['custom_unknown', 'loja', 'Loja'],
    ];
  }

  /**
   * Public checkout slug mapping must retain historic routing.
   */
  public function testCheckoutPathCompatibility(): void {
    self::assertSame(
      '/finalizar-compra/42/pagamento',
      CheckoutSlugMap::toPublic('/checkout/42/payment'),
    );
    self::assertSame(
      '/checkout/42/payment',
      CheckoutSlugMap::toInternal('/finalizar-compra/42/pagamento'),
    );
    self::assertSame(
      '/finalizar-compra/42/pagamento/return',
      CheckoutSlugMap::toPublic('/checkout/42/payment/return'),
    );
    self::assertSame(
      '/checkout/42/payment/cancel',
      CheckoutSlugMap::toInternal('/finalizar-compra/42/pagamento/cancel'),
    );
    self::assertNull(CheckoutSlugMap::toPublic('/loja'));
    self::assertNull(CheckoutSlugMap::toInternal('/donate'));
  }

}
