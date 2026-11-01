<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\EventSubscriber;

use Drupal\aculta_lms\Event\CertificateIssuedEvent;
use Drupal\aculta_learning_contracts\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\aculta_lms\Notice\LearningNoticeRecipientsInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts durable LMS events into the existing Messenger notice records.
 */
final class LearningNotificationSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LearningNoticeRecipientsInterface $recipients,
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CourseEnrollmentEvent::NAME => 'courseEnrollment',
      CertificateIssuedEvent::NAME => 'certificateIssued',
      CourseCompletedEvent::NAME => 'courseCompleted',
      AnswerEvaluatedEvent::NAME => 'answerEvaluated',
      AnswerSubmittedEvent::NAME => 'answerSubmitted',
      CourseContentReleasedEvent::NAME => 'contentReleased',
      EducatorFeedbackPublishedEvent::NAME => 'educatorFeedbackPublished',
      ActivityDeadlineAssignedEvent::NAME => 'activityDeadlineAssigned',
    ];
  }

  /**
   * Publishes the site and optional daily-digest enrollment notice.
   */
  public function courseEnrollment(CourseEnrollmentEvent $event): void {
    $this->publish('enrollment', 'group_relationship', $event->membershipUuid, (int) $event->accountId);
  }

  /**
   * Publishes the site and optional daily-digest certificate notice.
   */
  public function certificateIssued(CertificateIssuedEvent $event): void {
    $this->publish('certificate', 'aculta_course_certificate', $event->certificateUuid, (int) $event->accountId);
  }

  /**
   * Records completion for the site and shared daily digest.
   */
  public function courseCompleted(CourseCompletedEvent $event): void {
    $this->publish(
      'completion',
      'lms_course_status',
      $event->sourceUuid,
      (int) $event->accountId,
      '1',
      $event->completedAt,
    );
  }

  /**
   * Publishes the new result for the learner's site and digest notices. */
  public function answerEvaluated(AnswerEvaluatedEvent $event): void {
    try {
      $this->publish(
        'evaluation',
        'lms_answer',
        $event->answerUuid,
        (int) $event->accountId,
        $event->publicationVersion,
      );
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not record LMS evaluation notice: @message', ['@message' => $exception->getMessage()]);
    }
  }

  /**
   * Records a pending response for course educators.
   */
  public function answerSubmitted(AnswerSubmittedEvent $event): void {
    try {
      $this->recipients->publishSubmissionNoticesForEducators($event->answerUuid, $event->publicationVersion);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish LMS submission notice (@exception).', [
        '@exception' => $exception::class,
      ]);
    }
  }

  /**
   * Records newly available published course content for current learners.
   */
  public function contentReleased(CourseContentReleasedEvent $event): void {
    try {
      $this->recipients->publishContentReleaseNoticesForLearners(
        $event->contentUuid,
        $event->contentEntityType,
        $event->publicationVersion,
      );
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish LMS content release notice (@exception).', [
        '@exception' => $exception::class,
      ]);
    }
  }

  /**
   * Records newly published educator feedback for the answer owner. */
  public function educatorFeedbackPublished(EducatorFeedbackPublishedEvent $event): void {
    try {
      $this->recipients->publishEducatorFeedbackNoticeForLearner($event->commentUuid, $event->publicationVersion);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish LMS educator feedback notice (@exception).', [
        '@exception' => $exception::class,
      ]);
    }
  }

  /**
   * Records a class-specific activity deadline for its current learners. */
  public function activityDeadlineAssigned(ActivityDeadlineAssignedEvent $event): void {
    try {
      $this->recipients->publishActivityDeadlineNoticesForLearners($event->deadlineUuid, $event->publicationVersion);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish LMS activity deadline notice (@exception).', [
        '@exception' => $exception::class,
      ]);
    }
  }

  /**
   * Persists one deduplicated notice without breaking the LMS transaction.
   */
  private function publish(
    string $noticeType,
    string $sourceEntityType,
    string $sourceUuid,
    int $uid,
    string $version = '1',
    ?\DateTimeImmutable $occurredAt = NULL,
  ): void {
    try {
      $eventType = 'lms.' . $noticeType;
      $this->publisher->publish(new MessengerNoticeEvent(
        $eventType,
        'aculta_lms',
        $sourceEntityType,
        $sourceUuid,
        $version,
        $uid,
        'account',
        $occurredAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        implode(':', [$eventType, $sourceUuid, $version, $uid]),
        ['notice_type' => $noticeType, 'mail_policy' => 'user_preference'],
      ));
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish LMS notice @type (@exception).', [
        '@type' => $noticeType,
        '@exception' => $exception::class,
      ]);
    }
  }

}
