<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Xapi\XapiProgressAccess;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests ownership checks for xAPI progress references.
 */
#[CoversClass(XapiProgressAccess::class)]
final class XapiProgressAccessTest extends UnitTestCase {

  /**
   * Tests access for the progress owner.
   */
  public function testAllowsOnlyOwningUserForAcultaProgress(): void {
    $progress = $this->progress(42);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(17)->willReturn($progress);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_lms_progress')->willReturn($storage);

    self::assertTrue((new XapiProgressAccess($manager))->isOwnedBy(17, 42));
  }

  /**
   * Tests that another user cannot use the progress reference.
   */
  public function testRejectsAnotherUsersProgress(): void {
    $progress = $this->progress(42);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(17)->willReturn($progress);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_progress')->willReturn($storage);

    self::assertFalse((new XapiProgressAccess($manager))->isOwnedBy(17, 43));
  }

  /**
   * Tests missing progress records and invalid identifiers.
   */
  public function testRejectsMissingProgressAndInvalidIdentifiers(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(17)->willReturn(NULL);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_lms_progress')->willReturn($storage);
    $access = new XapiProgressAccess($manager);

    self::assertFalse($access->isOwnedBy(17, 42));
    self::assertFalse($access->isOwnedBy(0, 42));
    self::assertFalse($access->isOwnedBy(17, 0));
  }

  /**
   * Creates a progress entity double for a user.
   */
  private function progress(int $uid): ContentEntityInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['value' => $uid]]);
    $progress = $this->createMock(ContentEntityInterface::class);
    $progress->method('getEntityTypeId')->willReturn('aculta_lms_progress');
    $progress->method('hasField')->with('uid')->willReturn(TRUE);
    $progress->method('get')->with('uid')->willReturn($field);
    return $progress;
  }

}
