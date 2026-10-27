<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit\Shipping;

use Drupal\aculta_commerce\Shipping\PackageTypeManager;
use Drupal\physical\LengthUnit;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_commerce\Shipping\PackageTypeManager
 *
 * @group aculta_commerce
 */
final class PackageTypeManagerTest extends UnitTestCase {

  /**
   * Built-in package types expose weight and dimensions to providers.
   */
  public function testPackageTypeDimensions(): void {
    $manager = new PackageTypeManager();
    $box = $manager->getPackageType('custom_box');
    $this->assertSame('custom_box', $box->getId());
    $this->assertSame('0', $box->getWeight()->getNumber());
    $this->assertSame('0', $box->getLength()->getNumber());
    $this->assertSame(LengthUnit::CENTIMETER, $box->getLength()->getUnit());

    $letter = $manager->getPackageType('letter');
    $this->assertSame('22', $letter->getLength()->getNumber());
    $this->assertSame('11', $letter->getWidth()->getNumber());
    $this->assertSame('1', $letter->getHeight()->getNumber());
  }

  /**
   * Unknown IDs fall back to the custom box, never to a rate.
   */
  public function testUnknownPackageTypeFallsBack(): void {
    $manager = new PackageTypeManager();
    $this->assertSame('custom_box', $manager->getPackageType('unknown')->getId());
  }

}
