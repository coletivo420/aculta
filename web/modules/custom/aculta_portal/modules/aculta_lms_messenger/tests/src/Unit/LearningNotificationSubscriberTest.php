<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;
use Psr\Log\LoggerInterface;
use Drupal\aculta_lms\Event\CertificateIssuedEvent;
use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\aculta_lms_messenger\EventSubscriber\LearningNotificationSubscriber;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * @coversDefaultClass \Drupal\aculta_lms_messenger\EventSubscriber\LearningNotificationSubscriber
 */
#[CoversClass(LearningNotificationSubscriber::class)]
#[Group('aculta_lms_messenger')]
final class LearningNotificationSubscriberTest extends UnitTestCase {

  /**
   * Confirms that a persisted enrollment becomes a Messenger notice.
   */
  public function testEnrollmentCreatesRequiredSiteNoticeAndOptionalDigest(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects($this->once())
      ->method('publish')
      ->with(self::callback(static fn(MessengerNoticeEvent $notice): bool =>
        $notice->eventType() === 'lms.enrollment'
        && $notice->sourceEntityUuid() === 'membership-uuid'
        && $notice->recipientUid() === 27
        && $notice->metadata()['mail_policy'] === 'user_preference'
      ));

    $subscriber = new LearningNotificationSubscriber($manager, $publisher, new NullLogger());
    $subscriber->courseEnrollment(new CourseEnrollmentEvent('membership-uuid', '10', '11', '27'));
  }

  /**
   * Confirms that a certificate issue becomes a Messenger notice.
   */
  public function testCertificateCreatesRequiredSiteNoticeAndOptionalDigest(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects($this->once())
      ->method('publish')
      ->with(self::callback(static fn(MessengerNoticeEvent $notice): bool =>
        $notice->eventType() === 'lms.certificate'
        && $notice->sourceEntityUuid() === 'certificate-uuid'
        && $notice->recipientUid() === 27
        && $notice->metadata()['mail_policy'] === 'user_preference'
      ));

    $subscriber = new LearningNotificationSubscriber($manager, $publisher, new NullLogger());
    $subscriber->certificateIssued(new CertificateIssuedEvent('certificate-uuid', '27'));
  }

  /**
   * Confirms course completion is site-only and enters the shared digest.
   */
  public function testCompletionCreatesSiteNoticeWithoutTransactionalEmail(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects($this->once())
      ->method('publish')
      ->with(self::callback(static fn(MessengerNoticeEvent $notice): bool =>
        $notice->eventType() === 'lms.completion'
        && $notice->sourceEntityUuid() === 'status-uuid'
        && $notice->recipientUid() === 27
        && $notice->metadata()['mail_policy'] === 'user_preference'
      ));

    $subscriber = new LearningNotificationSubscriber($manager, $publisher, new NullLogger());
    $subscriber->courseCompleted(new CourseCompletedEvent(
      'status-uuid',
      '10',
      '27',
      new \DateTimeImmutable('2026-10-20T12:00:00+00:00'),
    ));
  }

  /**
   * An evaluated answer creates only the site and digest notices. */
  public function testEvaluationCreatesSiteNoticeWithoutTransactionalEmail(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects($this->once())
      ->method('publish')
      ->with(self::callback(static fn(MessengerNoticeEvent $notice): bool =>
        $notice->eventType() === 'lms.evaluation'
        && $notice->sourceEntityUuid() === 'answer-uuid'
        && $notice->sourceRevisionOrVersion() === str_repeat('a', 32)
        && $notice->metadata()['mail_policy'] === 'user_preference'
      ));

    $subscriber = new LearningNotificationSubscriber($manager, $publisher, new NullLogger());
    $subscriber->answerEvaluated(new AnswerEvaluatedEvent('answer-uuid', '27', str_repeat('a', 32)));
  }

  /**
   * A submitted response is routed to the notice manager for current graders.
   */
  public function testSubmissionNotifiesCurrentCourseEducators(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('publishSubmissionNoticesForEducators')
      ->with('answer-uuid', str_repeat('b', 32));

    $subscriber = new LearningNotificationSubscriber($manager, $this->publisher(), new NullLogger());
    $subscriber->answerSubmitted(new AnswerSubmittedEvent('answer-uuid', str_repeat('b', 32)));
  }

  /**
   * Routes a persisted content release into the learner notice provider.
   */
  public function testContentReleaseNotifiesCurrentLearners(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('publishContentReleaseNoticesForLearners')
      ->with('lesson-uuid', 'lms_lesson', str_repeat('c', 32));

    $subscriber = new LearningNotificationSubscriber($manager, $this->publisher(), new NullLogger());
    $subscriber->contentReleased(new CourseContentReleasedEvent('lms_lesson', 'lesson-uuid', str_repeat('c', 32)));
  }

  /**
   * Published educator feedback is routed to the learner notice provider. */
  public function testEducatorFeedbackNotifiesAnswerOwner(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('publishEducatorFeedbackNoticeForLearner')
      ->with('comment-uuid', str_repeat('d', 32));

    $subscriber = new LearningNotificationSubscriber($manager, $this->publisher(), new NullLogger());
    $subscriber->educatorFeedbackPublished(new EducatorFeedbackPublishedEvent('comment-uuid', str_repeat('d', 32)));
  }

  /**
   * A saved deadline is routed to the current learners of its class. */
  public function testActivityDeadlineNotifiesCurrentClassLearners(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('publishActivityDeadlineNoticesForLearners')
      ->with('deadline-uuid', str_repeat('e', 32));

    $subscriber = new LearningNotificationSubscriber($manager, $this->publisher(), new NullLogger());
    $subscriber->activityDeadlineAssigned(new ActivityDeadlineAssignedEvent('deadline-uuid', str_repeat('e', 32)));
  }

  /**
   * Confirms notification failure does not escape the source event.
   */
  public function testPersistFailureIsLoggedAndDoesNotBreakSourceTransaction(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->method('publish')->willThrowException(new \RuntimeException('dispatch unavailable'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');

    $subscriber = new LearningNotificationSubscriber($manager, $publisher, $logger);
    $subscriber->certificateIssued(new CertificateIssuedEvent('certificate-uuid', '27'));
    $this->addToAssertionCount(1);
  }

  /**
   * Creates a publisher mock for event routes not under test.
   */
  private function publisher(): MessengerNoticePublisherInterface {
    return $this->createMock(MessengerNoticePublisherInterface::class);
  }

}
