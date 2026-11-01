<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\aculta_messenger\Integration\Lms\Plugin\QueueWorker\ActivityDeadlineReminderWorker;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds upcoming class activity deadlines to the Drupal queue.
 */
final class ActivityDeadlineReminderCronHooks {

  private const REMINDER_WINDOW = 86400;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'queue')]
    private readonly QueueFactory $queueFactory,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.channel.aculta_messenger')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Queues deadlines that enter their final 24 hours.
   */
  #[Hook('cron')]
  public function cron(): void {
    $now = $this->time->getRequestTime();
    $start = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $now);
    $end = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $now + self::REMINDER_WINDOW);
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin_id', 'lms_class_activity_due')
      ->condition('field_activity_due_at', $start, '>=')
      ->condition('field_activity_due_at', $end, '<=')
      ->execute();
    if ($ids === []) {
      return;
    }
    $queue = $this->queueFactory->get(ActivityDeadlineReminderWorker::QUEUE_ID);
    foreach ($storage->loadMultiple($ids) as $relationship) {
      if (!$relationship instanceof GroupRelationshipInterface) {
        continue;
      }
      $fieldValue = $relationship->get('field_activity_due_at')->getValue()[0]['value'] ?? NULL;
      if (!is_string($fieldValue) || $fieldValue === '') {
        continue;
      }
      $queued = $queue->createItem([
        'deadline_uuid' => (string) $relationship->uuid(),
        'event_version' => ActivityDeadlineAssignedEvent::versionFor(
          (string) $relationship->uuid(),
          $fieldValue,
          (int) $relationship->getChangedTime(),
        ),
      ]);
      if ($queued === FALSE) {
        $this->logger->error('Could not queue LMS activity deadline reminder for @uuid.', [
          '@uuid' => $relationship->uuid(),
        ]);
      }
    }
  }

}
