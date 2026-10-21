<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_messenger\EventSubscriber;

use Drupal\aculta_lms\Event\CertificateIssuedEvent;
use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts durable LMS events into the existing Messenger notice records.
 */
final class LearningNotificationSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LearningNoticeManagerInterface $notices,
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
    ];
  }

  /**
   * Handles a persisted enrollment and its transactional email.
   */
  public function courseEnrollment(CourseEnrollmentEvent $event): void {
    $this->record($event->membershipUuid, 'enrollment', (int) $event->accountId);
  }

  /**
   * Handles a persisted certificate issue and its transactional email.
   */
  public function certificateIssued(CertificateIssuedEvent $event): void {
    $this->record($event->certificateUuid, 'certificate', (int) $event->accountId);
  }

  /**
   * Records completion for the site and shared daily digest.
   */
  public function courseCompleted(CourseCompletedEvent $event): void {
    $this->record($event->sourceStatusUuid, 'completion', (int) $event->accountId, FALSE);
  }

  /**
   * Persists one deduplicated notice without breaking the LMS transaction.
   */
  private function record(string $sourceUuid, string $type, int $uid, bool $sendEmail = TRUE): void {
    try {
      $this->notices->record($sourceUuid, $type, $uid, $sendEmail);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not persist LMS notification @type: @message', [
        '@type' => $type,
        '@message' => $exception->getMessage(),
      ]);
    }
  }

}
