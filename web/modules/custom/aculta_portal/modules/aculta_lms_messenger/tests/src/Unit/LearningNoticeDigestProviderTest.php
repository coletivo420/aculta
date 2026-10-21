<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms_messenger\LearningNoticeManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * Tests real LMS Message digest-provider revalidation.
 */
#[CoversClass(LearningNoticeManager::class)]
#[Group('aculta_lms_messenger')]
final class LearningNoticeDigestProviderTest extends UnitTestCase {

  /**
   * Drops an orphaned certificate digest row without exposing a stale notice.
   */
  public function testStaleCertificateDigestIsDiscarded(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('27');

    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(LearningNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn('27');
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => $this->field('value', match ($field) {
      'field_aculta_lms_event_type' => 'certificate',
      'field_aculta_lms_source_uuid' => 'removed-certificate-uuid',
      default => throw new \InvalidArgumentException('Unexpected field: ' . $field),
    }));
    $message->expects(self::once())
      ->method('set')
      ->with('field_aculta_lms_digest_sent', 1)
      ->willReturnSelf();
    $message->expects(self::once())->method('save')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $message_storage = $this->createMock(EntityStorageInterface::class);
    $message_storage->method('getQuery')->willReturn($query);
    $message_storage->method('loadMultiple')->with([1])->willReturn([$message]);
    $user_storage = $this->createMock(EntityStorageInterface::class);
    $user_storage->method('load')->with(27)->willReturn($account);
    $certificate_storage = $this->createMock(EntityStorageInterface::class);
    $certificate_storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['uuid' => 'removed-certificate-uuid'])
      ->willReturn([]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $user_storage,
      'message' => $message_storage,
      'aculta_lms_certificate' => $certificate_storage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $manager = new LearningNoticeManager(
      $entity_type_manager,
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
    );

    self::assertSame([], $manager->digestRowsFor(27));
  }

  /**
   * Drops a completion notice when its course reference has been removed.
   */
  public function testStaleCourseCompletionDigestIsDiscarded(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('27');
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(LearningNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn('27');
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => match ($field) {
      'field_aculta_lms_event_type' => $this->field('value', 'completion'),
      'field_aculta_lms_source_uuid' => $this->field('value', 'orphaned-course-status-uuid'),
      default => throw new \InvalidArgumentException('Unexpected field: ' . $field),
    });
    $message->expects(self::once())
      ->method('set')
      ->with('field_aculta_lms_digest_sent', 1)
      ->willReturnSelf();
    $message->expects(self::once())->method('save')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1]);
    $message_storage = $this->createMock(EntityStorageInterface::class);
    $message_storage->method('getQuery')->willReturn($query);
    $message_storage->method('loadMultiple')->with([1])->willReturn([$message]);
    $user_storage = $this->createMock(EntityStorageInterface::class);
    $user_storage->method('load')->with(27)->willReturn($account);
    $status = $this->createMock(CourseStatusInterface::class);
    $status->method('getUserId')->willReturn('27');
    $status->method('isFinished')->willReturn(TRUE);
    $status->method('getStatus')->willReturn(CourseStatusInterface::STATUS_PASSED);
    $status->method('get')->with(CourseStatusInterface::COURSE_FIELD)->willReturn($this->field('entity', NULL));
    $status->expects(self::never())->method('getCourse');
    $status_storage = $this->createMock(EntityStorageInterface::class);
    $status_storage->method('loadByProperties')
      ->with(['uuid' => 'orphaned-course-status-uuid'])
      ->willReturn([$status]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $user_storage,
      'message' => $message_storage,
      'lms_course_status' => $status_storage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $manager = new LearningNoticeManager(
      $entity_type_manager,
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
    );

    self::assertSame([], $manager->digestRowsFor(27));
  }

  /**
   * Returns a field-list stub with one string value.
   */
  private function field(string $property, mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with($property)->willReturn($value);
    return $field;
  }

}
