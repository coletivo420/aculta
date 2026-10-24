<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\MessengerCounts;
use Drupal\aculta_messenger\Service\NoticeFlagStateLoader;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Entity\Storage\FlaggingStorageInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\site_alert\Entity\SiteAlert;
use Drupal\site_alert\GetAlertsInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests visible alert counts and their batched personal Flag state.
 */
#[CoversClass(MessengerCounts::class)]
#[Group('aculta_messenger')]
final class MessengerCountsTest extends UnitTestCase {

  /**
   * Applies access before making one batch lookup for dismissals.
   */
  public function testVisibleAuthenticatedAlertsUseOneBatchFlagLookup(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $visibleAlerts = [
      $this->alert(17, AccessResult::allowed()),
      $this->alert(18, AccessResult::allowed()),
    ];
    $inaccessible = $this->alert(19, AccessResult::forbidden());
    $getAlerts = $this->createMock(GetAlertsInterface::class);
    $getAlerts->expects(self::once())->method('getActiveAlerts')->willReturn([
      ...$visibleAlerts,
      $inaccessible,
    ]);
    $dismissalFlag = $this->createMock(FlagInterface::class);
    $dismissalFlag->method('id')->willReturn('aculta_site_alert_dismissed');
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->expects(self::once())
      ->method('getFlagById')
      ->with('aculta_site_alert_dismissed')
      ->willReturn($dismissalFlag);
    $flagService->expects(self::never())->method('getFlagging');
    $flaggingStorage = $this->createMock(FlaggingStorageInterface::class);
    $flaggingStorage->expects(self::once())
      ->method('loadIsFlaggedMultiple')
      ->with($visibleAlerts, $account)
      ->willReturn([17 => ['aculta_site_alert_dismissed' => 'aculta_site_alert_dismissed']]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getStorage')->with('flagging')->willReturn($flaggingStorage);
    $counts = new MessengerCounts(
      $entityTypeManager,
      $flagService,
      $getAlerts,
      new NoticeFlagStateLoader($entityTypeManager),
    );

    self::assertSame(1, $counts->visibleActiveAlertCount($account, new CacheableMetadata()));
  }

  /**
   * Anonymous counts never query personal Flag storage.
   */
  public function testAnonymousAlertCountDoesNotLoadFlaggingStorage(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);
    $getAlerts = $this->createMock(GetAlertsInterface::class);
    $getAlerts->method('getActiveAlerts')->willReturn([$this->alert(17, AccessResult::allowed())]);
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->expects(self::once())->method('getFlagById')->willReturn(NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $counts = new MessengerCounts(
      $entityTypeManager,
      $flagService,
      $getAlerts,
      new NoticeFlagStateLoader($entityTypeManager),
    );

    self::assertSame(1, $counts->visibleActiveAlertCount($account, new CacheableMetadata()));
  }

  /**
   * Reuses the central list's access-filtered alerts and loaded Flag states.
   */
  public function testPreloadedCentralStateAvoidsReloadingAlertsOrFlags(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $visibleAlert = $this->alert(17, AccessResult::allowed());
    $getAlerts = $this->createMock(GetAlertsInterface::class);
    $getAlerts->expects(self::never())->method('getActiveAlerts');
    $dismissalFlag = $this->createMock(FlagInterface::class);
    $dismissalFlag->method('id')->willReturn('aculta_site_alert_dismissed');
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->method('getFlagById')->willReturn($dismissalFlag);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $counts = new MessengerCounts(
      $entityTypeManager,
      $flagService,
      $getAlerts,
      new NoticeFlagStateLoader($entityTypeManager),
    );

    self::assertSame(1, $counts->visibleActiveAlertCount(
      $account,
      new CacheableMetadata(),
      [$visibleAlert],
      [17 => []],
    ));
  }

  /**
   * Creates a cacheable Site Alert with a controlled access result.
   */
  private function alert(int $id, AccessResult $access): SiteAlert {
    $alert = $this->createMock(SiteAlert::class);
    $alert->method('id')->willReturn($id);
    $alert->method('access')->with('view', self::anything(), TRUE)->willReturn($access);
    $alert->method('getCacheContexts')->willReturn([]);
    $alert->method('getCacheTags')->willReturn(['site_alert:' . $id]);
    $alert->method('getCacheMaxAge')->willReturn(-1);
    return $alert;
  }

}
