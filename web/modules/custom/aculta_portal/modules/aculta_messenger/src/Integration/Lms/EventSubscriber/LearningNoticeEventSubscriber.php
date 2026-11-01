<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Consumes LMS notice events through Messenger's public event contract.
 */
final class LearningNoticeEventSubscriber implements EventSubscriberInterface {

  private const NOTICE_TYPES = [
    'enrollment' => 'group_relationship',
    'certificate' => 'aculta_course_certificate',
    'completion' => 'lms_course_status',
    'evaluation' => 'lms_answer',
    'submission' => 'lms_answer',
    'course_release' => 'group',
    'lesson_release' => 'lms_lesson',
    'activity_release' => 'lms_activity',
    'feedback' => 'comment',
    'deadline' => 'group_relationship',
    'deadline_reminder' => 'group_relationship',
  ];

  public function __construct(
    private readonly LearningNoticeManagerInterface $notices,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MessengerNoticeEvent::EVENT_NAME => 'consume',
    ];
  }

  /**
   * Persists a supported LMS event through the existing Message storage.
   */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_lms') {
      return;
    }
    $metadata = $event->metadata();
    $type = $metadata['notice_type'] ?? NULL;
    $mailPolicy = $metadata['mail_policy'] ?? NULL;
    if (!is_string($type) || !isset(self::NOTICE_TYPES[$type])
      || $event->eventType() !== 'lms.' . $type
      || $event->sourceEntityType() !== self::NOTICE_TYPES[$type]
      || $mailPolicy !== 'user_preference') {
      return;
    }

    try {
      $mode = $this->preferences->emailMode($event->recipientUid(), 'aprendizagem');
      $this->notices->record(
        $event->sourceEntityUuid(),
        $type,
        $event->recipientUid(),
        $mode === AvisoPreferencias::EMAIL_IMMEDIATE,
        $event->sourceRevisionOrVersion() ?? '1',
        $mode === AvisoPreferencias::EMAIL_DIGEST,
      );
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not consume LMS notice event: @type.', [
        '@type' => $exception::class,
      ]);
    }
  }

}
