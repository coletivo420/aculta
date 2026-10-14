<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/** Queues private-message notices after the upstream event is raised. */
final class PrivateMessageNotificationHooks {

  public function __construct(
    private readonly QueueFactoryInterface $queueFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Enqueues only the private-message ID; the worker resolves the saved thread.
   *
   * Private Message invokes this hook from addMessage() before saving a new
   * thread. Queue processing therefore resolves the relationship after the
   * native form has completed its save operation.
   */
  #[Hook('private_message_new_message')]
  public function newMessage(PrivateMessageInterface $privateMessage, PrivateMessageThreadInterface $thread): void {
    $itemId = $this->queueFactory
      ->get('aculta_messenger_private_message_notification')
      ->createItem(['private_message_id' => (int) $privateMessage->id()]);

    if ($itemId === FALSE) {
      $this->loggerFactory->get('aculta_messenger')->error('Could not queue a private message notification.');
    }
  }

}
