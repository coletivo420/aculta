<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Lms\Unit;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Notice\LearningEligibility;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/** Tests stale ACULTA answer links close safely when the activity is gone. */
#[CoversClass(LearningNoticeManager::class)]
#[Group('aculta_messenger')]
final class LearningNoticeStaleAnswerTest extends UnitTestCase {

  public function testSubmissionWithMissingActivityIsIgnoredSafely(): void {
    $answer = $this->createMock(ContentEntityInterface::class);
    $answer->method('getEntityTypeId')->willReturn('aculta_lms_answer');
    $answer->method('id')->willReturn('17');
    $answer->method('uuid')->willReturn('stale-answer-uuid');
    $answer->method('get')->willReturnCallback(fn(string $name): FieldItemListInterface => match ($name) {
      'state' => $this->field('submitted'),
      'activity_id' => $this->field('404'),
      default => throw new \InvalidArgumentException('Unexpected field: ' . $name),
    });

    $answerStorage = $this->createMock(EntityStorageInterface::class);
    $answerStorage->expects(self::once())->method('loadByProperties')
      ->with(['uuid' => ['stale-answer-uuid']])->willReturn([$answer]);
    $answerStorage->expects(self::once())->method('resetCache')->with(['17']);
    $answerStorage->expects(self::once())->method('loadMultiple')->with(['17'])->willReturn([$answer]);
    $activityStorage = $this->createMock(EntityStorageInterface::class);
    $activityStorage->expects(self::once())->method('load')->with(404)->willReturn(NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))->method('getStorage')->willReturnMap([
      ['aculta_lms_answer', $answerStorage],
      ['aculta_lms_activity', $activityStorage],
    ]);

    $eligibility = new LearningEligibility(
      $entityTypeManager,
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
    );
    $manager = new LearningNoticeManager(
      $entityTypeManager,
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
      $this->createMock(LockBackendInterface::class),
      $eligibility,
    );
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(LearningNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn('31');
    $message->method('get')->willReturnCallback(fn(string $name): FieldItemListInterface => match ($name) {
      'field_aculta_lms_event_type' => $this->field('submission'),
      'field_aculta_lms_source_uuid' => $this->field('stale-answer-uuid'),
      default => throw new \InvalidArgumentException('Unexpected field: ' . $name),
    });
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('31');

    self::assertNull($manager->resolve($message, $account));
  }

  private function field(mixed $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly mixed $scalar) {
        parent::__construct(DataDefinition::create('field_item_list'));
      }

      public function __get($property_name): mixed {
        return $property_name === 'value' ? $this->scalar : parent::__get($property_name);
      }

    };
  }

}
