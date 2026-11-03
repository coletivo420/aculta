<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_lms\Notice\LearningNoticeRecipients;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that LMS producers fail closed on stale source references.
 */
#[CoversClass(LearningNoticeRecipients::class)]
#[Group('aculta_lms')]
final class LearningNoticeRecipientsTest extends UnitTestCase {

  /**
   * A pending submission with no current course is ignored safely.
   */
  public function testSubmissionWithStaleCourseDoesNotPublish(): void {
    $answer = $this->createMock(ContentEntityBase::class);
    $answer->method('getEntityTypeId')->willReturn('aculta_lms_answer');
    $answer->method('get')->with('state')->willReturn((object) ['value' => 'submitted']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['uuid' => 'stale-answer-uuid'])
      ->willReturn([$answer]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('aculta_lms_answer')
      ->willReturn($storage);

    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseForAnswer')->willReturn(NULL);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');

    $recipients = new LearningNoticeRecipients($entityTypeManager, $eligibility, $publisher);
    $recipients->publishSubmissionNoticesForEducators('stale-answer-uuid', 'version-one');
  }

}
