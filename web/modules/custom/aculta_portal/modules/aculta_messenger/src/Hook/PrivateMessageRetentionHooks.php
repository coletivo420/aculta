<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies the six-month inactivity retention policy to private conversations. */
final class PrivateMessageRetentionHooks {

  private const CURSOR_KEY = 'aculta_messenger.private_message_retention_cursor';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.factory')]
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'aculta_messenger.conversation_policy')]
    private readonly ConversationPolicy $policy,
    #[Autowire(service: 'state')]
    private readonly StateInterface $state,
  ) {}

  /**
 * Deletes dormant threads through Private Message's entity lifecycle. */
  #[Hook('cron')]
  public function expireDormantThreads(): void {
    if (!$this->entityTypeManager->hasDefinition('private_message_thread')) {
      return;
    }

    $cutoff = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->modify('-' . $this->policy->retentionMonths() . ' months')
      ->getTimestamp();
    $cursor = max(0, (int) $this->state->get(self::CURSOR_KEY, 0));
    $storage = $this->entityTypeManager->getStorage('private_message_thread');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('updated', $cutoff, '<=')
      ->condition('id', $cursor, '>')
      ->sort('id', 'ASC')
      ->range(0, $this->policy->cleanupBatch())
      ->execute();
    if ($ids === []) {
      $this->state->delete(self::CURSOR_KEY);
      return;
    }

    $deleted = 0;
    foreach ($storage->loadMultiple($ids) as $thread) {
      if (!$thread instanceof PrivateMessageThreadInterface || (int) $thread->get('updated')->value > $cutoff) {
        continue;
      }
      $transaction = $this->database->startTransaction();
      try {
        // Capture source IDs first. The contrib thread lifecycle removes its
        // messages, so notification records must be deleted only after that
        // succeeds. The transaction restores both sides if either deletion
        // fails.
        $messageIds = array_values(array_map(
          static fn($message): int => (int) $message->id(),
          $thread->getMessages(TRUE),
        ));
        $thread->delete();
        $this->deleteNotificationsFor($messageIds);
        $transaction->commitOrRelease();
        $deleted++;
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        $this->loggerFactory->get('aculta_messenger')->error('A private conversation could not be removed by the six-month retention task: @type.', [
          '@type' => $exception::class,
        ]);
      }
    }

    if ($deleted > 0) {
      $this->loggerFactory->get('aculta_messenger')->notice('Removed @count private conversations after six months without activity.', ['@count' => $deleted]);
    }

    // Advance past failed entities as well, so the same oldest page cannot
    // starve later conversations. A short page means the scan reached its end;
    // failed rows are retried from the start during the next cron pass.
    if (count($ids) < $this->policy->cleanupBatch()) {
      $this->state->delete(self::CURSOR_KEY);
    }
    else {
      $this->state->set(self::CURSOR_KEY, max(array_map('intval', $ids)));
    }
  }

  /**
   * Deletes the Messenger notification records that reference this thread's private messages. */
  private function deleteNotificationsFor(array $messageIds): void {
    if ($messageIds === []) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $notificationIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', 'aculta_msg_private_notification')
      ->condition('field_aculta_messenger_source_pm', $messageIds, 'IN')
      ->execute();
    if ($notificationIds !== []) {
      $storage->delete($storage->loadMultiple($notificationIds));
    }
  }

}
