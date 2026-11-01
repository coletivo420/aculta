<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\MessengerCounts;
use Drupal\aculta_messenger\Service\NoticeFlagStateLoader;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
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
    $readFlag = $this->createMock(FlagInterface::class);
    $readFlag->method('id')->willReturn('aculta_site_alert_read');
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->expects(self::exactly(2))
      ->method('getFlagById')
      ->willReturnMap([
        ['aculta_site_alert_dismissed', $dismissalFlag],
        ['aculta_site_alert_read', $readFlag],
      ]);
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
   * Read active alerts do not contribute to the unified unread badge.
   */
  public function testReadActiveAlertIsExcludedFromUnreadCount(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $alert = $this->alert(17, AccessResult::allowed());
    $alerts = $this->createMock(GetAlertsInterface::class);
    $alerts->method('getActiveAlerts')->willReturn([$alert]);
    $dismissalFlag = $this->createMock(FlagInterface::class);
    $dismissalFlag->method('id')->willReturn('aculta_site_alert_dismissed');
    $readFlag = $this->createMock(FlagInterface::class);
    $readFlag->method('id')->willReturn('aculta_site_alert_read');
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->method('getFlagById')->willReturnMap([
      ['aculta_site_alert_dismissed', $dismissalFlag],
      ['aculta_site_alert_read', $readFlag],
    ]);
    $flaggingStorage = $this->createMock(FlaggingStorageInterface::class);
    $flaggingStorage->expects(self::once())
      ->method('loadIsFlaggedMultiple')
      ->with([$alert], $account)
      ->willReturn([17 => ['aculta_site_alert_read' => 'aculta_site_alert_read']]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getStorage')->with('flagging')->willReturn($flaggingStorage);
    $counts = new MessengerCounts(
      $entityTypeManager,
      $flagService,
      $alerts,
      new NoticeFlagStateLoader($entityTypeManager),
    );

    self::assertSame(0, $counts->visibleActiveAlertCount($account, new CacheableMetadata()));
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
    $readFlag = $this->createMock(FlagInterface::class);
    $readFlag->method('id')->willReturn('aculta_site_alert_read');
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->method('getFlagById')->willReturnMap([
      ['aculta_site_alert_dismissed', $dismissalFlag],
      ['aculta_site_alert_read', $readFlag],
    ]);
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
   * The unified badge combines visible alerts, persistent notices and adapters.
   */
  public function testTotalUnreadNoticeCountCombinesAllNoticeSources(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $alert = $this->alert(17, AccessResult::allowed());
    $alerts = $this->createMock(GetAlertsInterface::class);
    $alerts->method('getActiveAlerts')->willReturn([$alert]);

    $dismissFlag = $this->createMock(FlagInterface::class);
    $dismissFlag->method('id')->willReturn('aculta_site_alert_dismissed');
    $readFlag = $this->createMock(FlagInterface::class);
    $readFlag->method('id')->willReturn('aculta_site_alert_read');
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->willReturnMap([
      ['aculta_site_alert_dismissed', $dismissFlag],
      ['aculta_site_alert_read', $readFlag],
    ]);

    $flaggingStorage = $this->createMock(FlaggingStorageInterface::class);
    $flaggingStorage->method('loadIsFlaggedMultiple')->with([$alert], $account)->willReturn([]);
    $flaggingQuery = $this->createMock(QueryInterface::class);
    $flaggingQuery->method('accessCheck')->with(FALSE)->willReturnSelf();
    $flaggingQuery->method('condition')->willReturnSelf();
    $flaggingQuery->method('execute')->willReturn([]);
    $flaggingStorage->method('getQuery')->willReturn($flaggingQuery);

    $flagStorage = $this->createMock(EntityStorageInterface::class);
    $flagStorage->method('load')->with('aculta_notice_read')->willReturn($readFlag);
    $noticeQuery = $this->createMock(QueryInterface::class);
    $noticeQuery->method('accessCheck')->with(TRUE)->willReturnSelf();
    $noticeQuery->method('condition')->willReturnSelf();
    $noticeQuery->method('count')->willReturnSelf();
    $noticeQuery->method('execute')->willReturn(3);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($noticeQuery);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'flag' => $flagStorage,
      'flagging' => $flaggingStorage,
      'node' => $nodeStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $counts = new MessengerCounts(
      $entityTypeManager,
      $flags,
      $alerts,
      new NoticeFlagStateLoader($entityTypeManager),
    );

    self::assertSame(9, $counts->totalUnreadNoticeCount($account, 5));
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
