<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Lms\Unit;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManager;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * Covers serialization of idempotent LMS notice records.
 */
#[CoversClass(LearningNoticeManager::class)]
#[Group('aculta_messenger')]
final class LearningNoticeRecordLockTest extends UnitTestCase {

  /**
   * A concurrent replay throws before querying or creating a duplicate.
   */
  public function testReplayCannotEnterMessageLookupWhileLockIsHeld(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $accountStorage = $this->createMock(EntityStorageInterface::class);
    $accountStorage->expects(self::once())->method('load')->with(27)->willReturn($account);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::never())->method('getQuery');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $accountStorage,
      'message' => $messageStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $lock = $this->createMock(LockBackendInterface::class);
    $lockId = 'aculta_messenger:lms_notice:' . hash('sha256', implode("\0", [
      '27',
      'deadline',
      'source-uuid',
      'version-one',
    ]));
    $lock->expects(self::once())->method('acquire')->with($lockId, 30.0)->willReturn(FALSE);
    $lock->expects(self::never())->method('release');
    $manager = new LearningNoticeManager(
      $entityTypeManager,
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
      $this->createMock(MessengerNoticePublisherInterface::class),
      $lock,
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('LMS notice is currently being recorded.');
    $manager->record('source-uuid', 'deadline', 27, TRUE, 'version-one');
  }

}
