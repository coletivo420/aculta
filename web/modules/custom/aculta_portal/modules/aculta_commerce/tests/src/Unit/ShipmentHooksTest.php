<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Entity\Shipment;
use Drupal\aculta_commerce\Hook\ShipmentHooks;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests shipment lifecycle cleanup.
 */
#[CoversClass(ShipmentHooks::class)]
#[Group('aculta_commerce')]
final class ShipmentHooksTest extends UnitTestCase {

  /**
   * Anonymous shipping profiles are removed when their shipment is deleted.
   */
  public function testAnonymousShippingProfileIsDeleted(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->expects($this->once())->method('getOwnerId')->willReturn(NULL);
    $shipment = $this->createMock(Shipment::class);
    $shipment->expects($this->once())->method('getShippingProfile')->willReturn($profile);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('delete')->with([$profile]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())->method('getStorage')->with('profile')->willReturn($storage);

    (new ShipmentHooks($manager))->entityDelete($shipment);
  }

  /**
   * Profiles owned by a user remain after shipment deletion.
   */
  public function testOwnedShippingProfileIsPreserved(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->expects($this->once())->method('getOwnerId')->willReturn('42');
    $shipment = $this->createMock(Shipment::class);
    $shipment->method('getShippingProfile')->willReturn($profile);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->never())->method('getStorage');

    (new ShipmentHooks($manager))->entityDelete($shipment);
  }

}
