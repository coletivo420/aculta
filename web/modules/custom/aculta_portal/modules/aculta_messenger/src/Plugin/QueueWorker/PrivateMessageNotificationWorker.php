<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\QueueWorker;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Sends minimal, deduplicated private-message email notices. */
#[QueueWorker(
  id: 'aculta_messenger_private_message_notification',
  title: new TranslatableMarkup('ACULTA Messenger private message notification'),
  cron: ['time' => 30],
)]
final class PrivateMessageNotificationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const TEMPLATE = 'aculta_messenger_private_message_notification';

  private const SOURCE_FIELD = 'field_aculta_messenger_source_pm';

  private const STATUS_FIELD = 'field_aculta_messenger_delivery_status';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const MAX_ATTEMPTS = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly MessageNotifyInterface $messageNotify,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly LockBackendInterface $lock,
    private readonly UserDataInterface $userData,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('private_message.service'),
      $container->get('message_notify.sender'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('lock.persistent'),
      $container->get('user.data'),
      $container->get('config.factory'),
    );
  }

  /** {@inheritdoc} */
  public function processItem($data): void {
    if (!is_array($data)) {
      return;
    }
    $messageId = filter_var($data['private_message_id'] ?? NULL, FILTER_VALIDATE_INT);
    if ($messageId === FALSE || $messageId < 1) {
      return;
    }

    $privateMessage = $this->entityTypeManager
      ->getStorage('private_message')
      ->load($messageId);
    if (!$privateMessage instanceof PrivateMessageInterface) {
      return;
    }

    $thread = $this->privateMessageService->getThreadFromMessage($privateMessage);
    if (!$thread instanceof PrivateMessageThreadInterface) {
      // The hook fires before a new thread is saved; allow the queue backend to
      // retry when a concurrent cron process claimed the item too early.
      throw new \RuntimeException('Private message thread is not available yet.');
    }

    $senderId = (int) $privateMessage->getOwnerId();
    foreach ($thread->getMembersId() as $recipientId) {
      $recipientId = (int) $recipientId;
      if ($recipientId === $senderId) {
        continue;
      }

      $lockId = 'aculta_messenger:private_message:' . $messageId . ':' . $recipientId;
      if (!$this->lock->acquire($lockId, 60.0)) {
        throw new \RuntimeException('Private message notification is already being processed.');
      }

      try {
        $this->notifyRecipient($privateMessage, $thread, $messageId, $senderId, $recipientId);
      }
      finally {
        $this->lock->release($lockId);
      }
    }
  }

  /** Creates or retries the persisted event for one authorized recipient. */
  private function notifyRecipient(PrivateMessageInterface $privateMessage, PrivateMessageThreadInterface $thread, int $messageId, int $senderId, int $recipientId): void {
    $recipient = $this->entityTypeManager->getStorage('user')->load($recipientId);
    if ($recipient === NULL || !$recipient->isActive()
      || !$recipient->hasPermission('use private messaging system')
      || !$recipient->hasPermission('access user profiles')
      || !$thread->access('view', $recipient, TRUE)->isAllowed()
      || $this->isBlockedPair($senderId, $recipientId)) {
      return;
    }

    // Honor an explicit native Private Message email opt-out. The optional
    // contrib notifier is deliberately not enabled because its stock template
    // includes the private message body.
    $settings = $this->configFactory->get('private_message.settings');
    if (!$settings->get('enable_notifications')) {
      return;
    }
    $preference = $this->userData->get('private_message', $recipientId, 'receive_notification');
    if (($preference !== NULL && !(bool) $preference)
      || ($preference === NULL && !$settings->get('notify_by_default'))) {
      return;
    }
    if (!is_string($recipient->getEmail()) || $recipient->getEmail() === '') {
      return;
    }

    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $recipientId)
      ->condition(self::SOURCE_FIELD, $messageId)
      ->range(0, 1)
      ->execute();
    $notification = $ids === []
      ? NULL
      : $this->entityTypeManager->getStorage('message')->load(reset($ids));

    if ($notification !== NULL && $notification->get(self::STATUS_FIELD)->value === 'sent') {
      return;
    }

    // Send members to the Portuguese ACCOUNT communication center.
    $url = $this->domainPurposeManager->routeUrl('account', 'aculta_messenger.messages');
    if ($url === NULL) {
      if ($notification === NULL) {
        $notification = $this->entityTypeManager->getStorage('message')->create([
          'template' => self::TEMPLATE,
          'uid' => $recipientId,
          self::SOURCE_FIELD => $messageId,
          self::STATUS_FIELD => 'failed',
          self::ATTEMPTS_FIELD => self::MAX_ATTEMPTS,
        ]);
      }
      else {
        $notification->set(self::STATUS_FIELD, 'failed');
        $notification->set(self::ATTEMPTS_FIELD, self::MAX_ATTEMPTS);
      }
      $notification->save();
      return;
    }

    if ($notification === NULL) {
      $notification = $this->entityTypeManager->getStorage('message')->create([
        'template' => self::TEMPLATE,
        'uid' => $recipientId,
        self::SOURCE_FIELD => $messageId,
        self::STATUS_FIELD => 'pending',
        self::ATTEMPTS_FIELD => 0,
      ]);
    }

    $attempts = (int) $notification->get(self::ATTEMPTS_FIELD)->value;
    if ($attempts >= self::MAX_ATTEMPTS) {
      $notification->set(self::STATUS_FIELD, 'failed');
      $notification->save();
      return;
    }

    $attempts++;
    $notification->setArguments(['@account_url' => $url->toString()]);
    $notification->set(self::ATTEMPTS_FIELD, $attempts);
    $notification->save();

    if (!$this->messageNotify->send($notification, ['save on success' => FALSE, 'save on fail' => FALSE])) {
      $notification->set(self::STATUS_FIELD, 'failed');
      $notification->save();
      if ($attempts < self::MAX_ATTEMPTS) {
        throw new \RuntimeException('Private message email delivery failed.');
      }
      return;
    }

    $notification->set(self::STATUS_FIELD, 'sent');
    $notification->save();
  }

  /** Checks both block directions using the contrib entity API. */
  private function isBlockedPair(int $senderId, int $recipientId): bool {
    $storage = $this->entityTypeManager->getStorage('private_message_ban');
    foreach ([[$senderId, $recipientId], [$recipientId, $senderId]] as [$ownerId, $targetId]) {
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('owner', $ownerId)
        ->condition('target', $targetId)
        ->range(0, 1)
        ->execute();
      if ($ids !== []) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
