<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\address\Element\Address;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity mappings and variants of the site's shared address widget.
 */
#[Group('aculta_portal')]
final class AccountAddressFormTest extends UnitTestCase {

  /**
   * Tests Event Location field mapping.
   */
  public function testEventLocationUsesTheSharedAddressField(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('aculta_event_location');

    $this->assertSame('field_endereco', AccountAddressForm::fieldOf($entity));
  }

  /**
   * Tests the shared CEP widget adapter for external address formats.
   */
  public function testExternalAdapterCanUseSharedCepWidgetWithoutAccountNumberRule(): void {
    $tree = [
      'review' => [
        'address' => [
          '#type' => 'address',
          '#default_value' => [],
        ],
      ],
    ];

    $this->assertTrue(AccountAddressForm::apply($tree, '', FALSE, FALSE));
    $element = $tree['review']['address'];

    $this->assertSame('BR', $element['#default_value']['country_code']);
    $this->assertContains([Address::class, 'processAddress'], $element['#process']);
    $this->assertContains([Address::class, 'processGroup'], $element['#process']);
    $this->assertContains([AccountAddressForm::class, 'finish'], $element['#process']);
    $this->assertNotContains([StreetNumberField::class, 'processStreetNumber'], $element['#process']);
  }

  /**
   * Tests that Conta retains its number and S/N controls.
   */
  public function testAccountAddressVariantRetainsSharedNumberRule(): void {
    $element = ['#type' => 'address', '#default_value' => []];

    $this->assertTrue(AccountAddressForm::apply($element));
    $this->assertContains([StreetNumberField::class, 'processStreetNumber'], $element['#process']);
  }

}
