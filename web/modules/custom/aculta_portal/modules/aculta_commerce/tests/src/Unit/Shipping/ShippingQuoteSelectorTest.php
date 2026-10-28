<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit\Shipping;

use Drupal\aculta_commerce\Shipping\ShippingQuote;
use Drupal\aculta_commerce\Shipping\ShippingQuoteSelector;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(\Drupal\aculta_commerce\Shipping\ShippingQuoteSelector::class)]
#[Group('aculta_commerce')]
final class ShippingQuoteSelectorTest extends UnitTestCase {

  /**
   * Only quotes in the order currency and still valid are available.
   */
  public function testFiltersExpiredAndWrongCurrency(): void {
    $selector = new ShippingQuoteSelector();
    $quotes = [
      new ShippingQuote('m', 'a', 'Padrão', '13.50', 'BRL', 101),
      new ShippingQuote('m', 'b', 'Expirado', '11.00', 'BRL', 100),
      new ShippingQuote('m', 'c', 'Moeda divergente', '12.00', 'USD'),
    ];
    $this->assertSame([$quotes[0]], $selector->available($quotes, 'BRL', 100));
    $this->assertSame([], $selector->available($quotes, 'BRL', 102));
  }

  /**
   * Selection uses the server-side rate and does not invent a free rate.
   */
  public function testSelectionRejectsClientProvidedRate(): void {
    $selector = new ShippingQuoteSelector();
    $trusted = [new ShippingQuote('carrier', 'standard', 'Padrão', '18.90', 'BRL')];
    $this->assertSame($trusted[0], $selector->select($trusted, 'BRL', 100, 'carrier:standard'));
    $this->assertNull($selector->select($trusted, 'BRL', 100, 'carrier:free'));
    $this->assertNull($selector->select([], 'BRL', 100, 'carrier:standard'));
  }

  /**
   * Duplicate service identities cannot be silently overwritten.
   */
  public function testDuplicateQuotesFailClosed(): void {
    $this->expectException(\LogicException::class);
    (new ShippingQuoteSelector())->available([
      new ShippingQuote('m', 'a', 'Um', '10', 'BRL'),
      new ShippingQuote('m', 'a', 'Dois', '20', 'BRL'),
    ], 'BRL', 100);
  }

  /**
   * Currency strings must be canonical.
   */
  public function testInvalidCurrencyFailsClosed(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new ShippingQuoteSelector())->available([], 'brl', 100);
  }

  /**
   * Negative, exponential or float-like values are not legal amounts.
   */
  #[DataProvider('invalidAmountProvider')]
  public function testInvalidAmountRejected(string $amount): void {
    $this->expectException(\InvalidArgumentException::class);
    new ShippingQuote('m', 'a', 'Tarifa', $amount, 'BRL');
  }

  /**
   * Provides invalid amounts.
   *
   * @return array<string, array{string}>
   *   Invalid amount samples.
   */
  public static function invalidAmountProvider(): array {
    return [
      'negative' => ['-1.00'],
      'exponential' => ['1e3'],
      'comma' => ['1,00'],
      'excess precision' => ['1.0000001'],
      'whitespace' => [' 10'],
    ];
  }

  /**
   * Zero must be explicit, not inferred from an unavailable carrier.
   */
  public function testExplicitFreeQuoteIsPreserved(): void {
    $quote = new ShippingQuote('configured', 'pickup', 'Retirada autorizada', '0', 'BRL');
    $this->assertSame([$quote], (new ShippingQuoteSelector())->available([$quote], 'BRL', 100));
  }

}
