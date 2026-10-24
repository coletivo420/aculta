<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticeFlagStateLoader;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Entity\Storage\FlaggingStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests batched account-specific Flag state loading for notices.
 */
#[CoversClass(NoticeFlagStateLoader::class)]
#[Group('aculta_messenger')]
final class NoticeFlagStateLoaderTest extends UnitTestCase {

  /**
   * Loads all notice Flag state through one batch operation.
   */
  public function testAuthenticatedAccountLoadsFlagsForAllEntitiesAtOnce(): void {
    $entities = [
      $this->createMock(EntityInterface::class),
      $this->createMock(EntityInterface::class),
    ];
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $states = [
      17 => ['aculta_site_alert_read' => 'aculta_site_alert_read'],
      18 => ['aculta_site_alert_dismissed' => 'aculta_site_alert_dismissed'],
    ];
    $storage = $this->createMock(FlaggingStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadIsFlaggedMultiple')
      ->with($entities, $account)
      ->willReturn($states);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('flagging')
      ->willReturn($storage);

    self::assertSame($states, (new NoticeFlagStateLoader($entityTypeManager))->loadForAccount($entities, $account));
  }

  /**
   * Anonymous accounts do not trigger private Flag lookups.
   */
  public function testAnonymousAccountDoesNotLoadFlaggingStorage(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');

    self::assertSame([], (new NoticeFlagStateLoader($entityTypeManager))->loadForAccount([
      $this->createMock(EntityInterface::class),
    ], $account));
  }

  /**
   * An unavailable Flag storage implementation yields no private state.
   */
  public function testMissingFlaggingStorageReturnsNoState(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('flagging')->willReturn($storage);

    self::assertSame([], (new NoticeFlagStateLoader($entityTypeManager))->loadForAccount([
      $this->createMock(EntityInterface::class),
    ], $account));
  }

}
