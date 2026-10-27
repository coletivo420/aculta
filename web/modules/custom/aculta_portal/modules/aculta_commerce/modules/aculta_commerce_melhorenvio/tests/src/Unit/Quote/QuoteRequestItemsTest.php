<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Address\LogisticsAddress;
use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressPair;
use Drupal\aculta_commerce_melhorenvio\Quote\QuoteItem;
use Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage;
use Drupal\aculta_commerce_melhorenvio\Quote\QuoteRequestBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Carrinho com vários itens: um produto por item na API (a API monta as caixas).
 */
#[CoversClass(QuoteRequestBuilder::class)]
#[Group('aculta_commerce_melhorenvio')]
final class QuoteRequestItemsTest extends UnitTestCase {

  /**
   * Cada item sai com a própria medida, quantidade e valor da linha; nada é somado.
   */
  public function testEachItemKeepsItsOwnMeasuresAndQuantity(): void {
    $pair = new ShipmentAddressPair(
      new LogisticsAddress('74705130', '', '', FALSE, '', '', '', '', 'BR'),
      new LogisticsAddress('01310100', '', '', FALSE, '', '', '', '', 'BR'),
    );
    $items = [
      new QuoteItem('CAM-M', new QuotePackage(16.0, 2.0, 11.0, 0.3, 0.0), 2, 60.0),
      new QuoteItem('CAM-G', new QuotePackage(16.0, 2.0, 11.0, 0.35, 0.0), 1, 30.0),
    ];
    $payload = (new QuoteRequestBuilder())->buildItems($pair, $items);
    $this->assertCount(2, $payload['products']);
    $this->assertSame('CAM-M', $payload['products'][0]['id']);
    $this->assertSame(2, $payload['products'][0]['quantity']);
    $this->assertSame(60.0, $payload['products'][0]['insurance_value']);
    $this->assertSame(0.35, $payload['products'][1]['weight']);
    $this->assertSame(1, $payload['products'][1]['quantity']);
  }

}
