<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms_messenger\LearningNoticeManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * Tests notice producers fail closed when LMS answer references are stale.
 */
#[CoversClass(LearningNoticeManager::class)]
#[Group('aculta_lms_messenger')]
final class LearningNoticeStaleAnswerTest extends UnitTestCase {

  /**
   * A pending submission with no current LessonStatus is ignored safely.
   */
  public function testSubmissionWithMissingLessonStatusDoesNotThrow(): void {
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('isEvaluated')->willReturn(FALSE);
    $answer->method('getData')->willReturn(['essay' => 'private response']);
    $answer->expects(self::never())->method('getUserId');
    $answer->method('get')->with('lesson_status')->willReturn(
      new FieldItemList(DataDefinition::create('entity_reference')),
    );

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['uuid' => 'stale-answer-uuid'])
      ->willReturn([$answer]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('lms_answer')
      ->willReturn($storage);

    $manager = new LearningNoticeManager(
      $entityTypeManager,
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
      $this->createMock(MessengerNoticePublisherInterface::class),
    );

    $manager->publishSubmissionNoticesForEducators('stale-answer-uuid', 'version-one');
  }

}
