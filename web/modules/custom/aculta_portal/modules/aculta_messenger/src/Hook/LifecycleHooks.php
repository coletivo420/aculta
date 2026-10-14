<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactoryInterface;

/** Protects Messenger-owned notification records during module removal. */
final class LifecycleHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly QueueFactoryInterface $queueFactory,
  ) {}

  /** Prevents activating the upstream notifier with its private-body template. */
  #[Hook('module_preinstall')]
  public function preventPrivateBodyNotifierConflict(string $module, bool $isSyncing): void {
    $conflictingPair = ($module === 'aculta_messenger' && $this->moduleHandler->moduleExists('private_message_notify'))
      || ($module === 'private_message_notify' && $this->moduleHandler->moduleExists('aculta_messenger'));
    if ($conflictingPair) {
      throw new \RuntimeException('Private Message Notify cannot be enabled together with ACULTA Messenger because its default email template includes private message content.');
    }
  }

  /**
   * Prevents field/config removal while notification records still use them.
   *
   * The notification records are Message entities owned by the integration.
   * Their removal requires an explicit entity cleanup before module uninstall.
   */
  #[Hook('module_preuninstall')]
  public function protectNotificationData(string $module, bool $isSyncing): void {
    if ($module !== 'aculta_messenger') {
      return;
    }

    if ($this->queueFactory->get('aculta_messenger_private_message_notification')->numberOfItems() > 0) {
      throw new \RuntimeException('ACULTA Messenger cannot be uninstalled while private message notifications remain queued. Process or clear the queue explicitly first.');
    }
    if ($this->queueFactory->get('aculta_messenger_message_subscribe_event')->numberOfItems() > 0) {
      throw new \RuntimeException('ACULTA Messenger cannot be uninstalled while subscription events remain queued. Process or clear the queue explicitly first.');
    }

    $count = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', ['aculta_messenger_private_message_notification', 'aculta_messenger_forum_topic_published'], 'IN')
      ->count()
      ->execute();
    if ($count > 0) {
      throw new \RuntimeException('ACULTA Messenger cannot be uninstalled while notification Message entities remain. Remove or retain the records explicitly first.');
    }
  }

}
