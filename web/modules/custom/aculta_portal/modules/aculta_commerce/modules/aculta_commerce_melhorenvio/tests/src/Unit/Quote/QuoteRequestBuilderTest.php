<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Address\LogisticsAddress;
use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressPair;
use Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage;
use Drupal\aculta_commerce_melhorenvio\Quote\QuoteRequestBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Corpo da cotação no formato `products` da especificação.
 */
#[Group('aculta_commerce_melhorenvio')]
final class QuoteRequestBuilderTest extends UnitTestCase {

  /**
   * Endereço de cotação válido (só CEP).
   */
  private static function address(string $cep): LogisticsAddress {
    return new LogisticsAddress($cep, '', '', FALSE, '', '', '', '', 'BR');
  }

  /**
   * Origem, destino e um item que representa o pacote, com dimensões inteiras arredondadas para cima.
   */
  public function testBuildsProductsPayload(): void {
    $payload = (new QuoteRequestBuilder())->build(
      new ShipmentAddressPair(self::address('74003010'), self::address('01310100')),
      new QuotePackage(15.2, 10.0, 23.01, 0.8, 120.456),
    );
    self::assertSame(['postal_code' => '74003010'], $payload['from']);
    self::assertSame(['postal_code' => '01310100'], $payload['to']);
    self::assertSame([
      'id' => 'pacote',
      'width' => 16,
      'height' => 10,
      'length' => 24,
      'weight' => 0.8,
      'insurance_value' => 120.46,
      'quantity' => 1,
    ], $payload['products'][0]);
    self::assertSame(['receipt' => FALSE, 'own_hand' => FALSE], $payload['options']);
  }

  /**
   * Dimensões e peso zerados viram os mínimos aceitos; seguro negativo vira zero.
   */
  public function testZeroValuesBecomeMinimums(): void {
    $payload = (new QuoteRequestBuilder())->build(
      new ShipmentAddressPair(self::address('74003010'), self::address('01310100')),
      new QuotePackage(0, 0, 0, 0, -5),
    );
    $product = $payload['products'][0];
    self::assertSame([1, 1, 1], [$product['width'], $product['height'], $product['length']]);
    self::assertSame(0.01, $product['weight']);
    self::assertSame(0.0, $product['insurance_value']);
  }

  /**
   * Endereço inválido não gera payload.
   */
  public function testInvalidAddressIsRejected(): void {
    $invalid = new LogisticsAddress('', '', '', FALSE, '', '', '', '', '', ['postal_code_invalid']);
    $pair = new ShipmentAddressPair(self::address('74003010'), $invalid);
    self::assertSame('destino: postal_code_invalid', QuoteRequestBuilder::describeErrors($pair));
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('destino: postal_code_invalid');
    (new QuoteRequestBuilder())->build($pair, new QuotePackage(1, 1, 1, 1, 0));
  }

}
