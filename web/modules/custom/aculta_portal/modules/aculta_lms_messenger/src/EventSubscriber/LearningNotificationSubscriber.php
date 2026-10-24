<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_messenger\EventSubscriber;

use Drupal\aculta_lms\Event\CertificateIssuedEvent;
use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts durable LMS events into the existing Messenger notice records.
 */
final class LearningNotificationSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LearningNoticeManagerInterface $notices,
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
   * Handles a persisted enrollment and its transactional email.
   */
  public function courseEnrollment(CourseEnrollmentEvent $event): void {
    $this->publish('enrollment', 'group_relationship', $event->membershipUuid, (int) $event->accountId, TRUE);
  }

  /**
   * Handles a persisted certificate issue and its transactional email.
   */
  public function certificateIssued(CertificateIssuedEvent $event): void {
    $this->publish('certificate', 'aculta_course_certificate', $event->certificateUuid, (int) $event->accountId, TRUE);
  }

  /**
   * Records completion for the site and shared daily digest.
   */
  public function courseCompleted(CourseCompletedEvent $event): void {
    $this->publish(
      'completion',
      'lms_course_status',
      $event->sourceStatusUuid,
      (int) $event->accountId,
      FALSE,
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
        FALSE,
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
      $this->notices->publishSubmissionNoticesForEducators($event->answerUuid, $event->publicationVersion);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not record LMS submission notice: @message', ['@message' => $exception->getMessage()]);
    }
  }

  /**
   * Records newly available published course content for current learners.
   */
  public function contentReleased(CourseContentReleasedEvent $event): void {
    try {
      $this->notices->publishContentReleaseNoticesForLearners(
        $event->contentUuid,
        $event->contentEntityType,
        $event->publicationVersion,
      );
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not record LMS content release notice: @message', ['@message' => $exception->getMessage()]);
    }
  }

  /**
   * Records newly published educator feedback for the answer owner. */
  public function educatorFeedbackPublished(EducatorFeedbackPublishedEvent $event): void {
    try {
      $this->notices->publishEducatorFeedbackNoticeForLearner($event->commentUuid, $event->publicationVersion);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not record LMS educator feedback notice: @message', ['@message' => $exception->getMessage()]);
    }
  }

  /**
   * Records a class-specific activity deadline for its current learners. */
  public function activityDeadlineAssigned(ActivityDeadlineAssignedEvent $event): void {
    try {
      $this->notices->publishActivityDeadlineNoticesForLearners($event->deadlineUuid, $event->publicationVersion);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not record LMS activity deadline notice: @message', ['@message' => $exception->getMessage()]);
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
    bool $sendEmail,
    string $version = '1',
    ?\DateTimeImmutable $occurredAt = NULL,
  ): void {
    try {
      $eventType = 'lms.' . $noticeType;
      $mailPolicy = $sendEmail ? 'transactional' : 'site_digest';
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
        ['notice_type' => $noticeType, 'mail_policy' => $mailPolicy],
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
