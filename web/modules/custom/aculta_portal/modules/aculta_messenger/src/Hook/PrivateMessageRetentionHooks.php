<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies the six-month inactivity retention policy to private conversations. */
final class PrivateMessageRetentionHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.factory')]
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'aculta_messenger.conversation_policy')]
    private readonly ConversationPolicy $policy,
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
    $storage = $this->entityTypeManager->getStorage('private_message_thread');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('updated', $cutoff, '<=')
      ->sort('updated', 'ASC')
      ->range(0, $this->policy->cleanupBatch())
      ->execute();
    if ($ids === []) {
      return;
    }

    $deleted = 0;
    foreach ($storage->loadMultiple($ids) as $thread) {
      if (!$thread instanceof PrivateMessageThreadInterface || (int) $thread->getUpdatedTime() > $cutoff) {
        continue;
      }
      try {
        // Remove notice records in the same pass as the thread lifecycle so
        // members do not see notices for deleted conversations.
        $this->deleteNotificationsFor($thread);
        // The contrib entity's preDelete lifecycle deletes its referenced
        // private messages and participant history using its supported API.
        $thread->delete();
        $deleted++;
      }
      catch (\Throwable $exception) {
        $this->loggerFactory->get('aculta_messenger')->error('A private conversation could not be removed by the six-month retention task: @type.', [
          '@type' => $exception::class,
        ]);
      }
    }

    if ($deleted > 0) {
      $this->loggerFactory->get('aculta_messenger')->notice('Removed @count private conversations after six months without activity.', ['@count' => $deleted]);
    }
  }

  /**
   * Deletes the Messenger notification records that reference this thread's private messages. */
  private function deleteNotificationsFor(PrivateMessageThreadInterface $thread): void {
    $messageIds = array_values(array_map(
      static fn($message) => $message->id(),
      $thread->getMessages(TRUE),
    ));
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
