<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\QueueWorker;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_messenger\Mail\PrivateMessageNoticeMailType;
use Drupal\aculta_messenger\Service\MessengerMailDelivery;
use Drupal\aculta_messenger\Service\MessengerEmailLock;
use Drupal\aculta_messenger\Service\ConversationWriteLock;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends minimal, deduplicated private-message email notices. */
#[QueueWorker(
  id: 'aculta_msg_private_notification',
  title: new TranslatableMarkup('ACULTA Messenger private message notification'),
  cron: ['time' => 30],
)]
final class PrivateMessageNotificationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const TEMPLATE = 'aculta_msg_private_notification';

  private const SOURCE_FIELD = 'field_aculta_messenger_source_pm';

  private const MAILER_MANAGED_FIELD = 'field_aculta_mailer_managed';

  private const STATUS_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const DIGEST_FIELD = 'field_aculta_pm_digest_sent';

  private const MAX_ATTEMPTS = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly ConversationWriteLock $conversationWriteLock,
    private readonly MessageNotifyInterface $messageNotify,
    private readonly AcultaMailerInterface $mailer,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly LockBackendInterface $lock,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly EmailConfirmationPolicy $emailPolicy,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('private_message.service'),
      $container->get('aculta_messenger.conversation_write_lock'),
      $container->get('message_notify.sender'),
      $container->get('aculta_mailer.mailer'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('lock.persistent'),
      $container->get('aculta_messenger.aviso_preferencias'),
      $container->get('aculta_portal.email_confirmation_policy'),
    );
  }

  /**
   * {@inheritdoc} */
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

    $threadId = (int) $thread->id();
    if ($threadId < 1) {
      throw new \RuntimeException('Private message thread is not persisted yet.');
    }
    // Retention uses this same lock. Hold it through recipient revalidation
    // and delivery so a dormant thread cannot be deleted after access was
    // checked but before its queued email is sent.
    $freshThread = $this->conversationWriteLock->acquire($thread);
    if (!$freshThread instanceof PrivateMessageThreadInterface) {
      throw new \RuntimeException('Private message conversation is being updated.');
    }
    try {
      $senderId = (int) $privateMessage->getOwnerId();
      $mailerManaged = ($data['mailer_managed'] ?? FALSE) === TRUE;
      foreach ($freshThread->getMembersId() as $recipientId) {
        $recipientId = (int) $recipientId;
        if ($recipientId === $senderId) {
          continue;
        }

        $lockId = 'aculta_messenger:private_message:' . $messageId . ':' . $recipientId;
        if (!$this->lock->acquire($lockId, MessengerEmailLock::TIMEOUT)) {
          throw new \RuntimeException('Private message notification is already being processed.');
        }

        try {
          $this->notifyRecipient($privateMessage, $freshThread, $messageId, $senderId, $recipientId, $mailerManaged);
        }
        finally {
          $this->lock->release($lockId);
        }
      }
    }
    finally {
      $this->conversationWriteLock->release($threadId);
    }
  }

  /**
   * Creates or retries the persisted event for one authorized recipient. */
  private function notifyRecipient(PrivateMessageInterface $privateMessage, PrivateMessageThreadInterface $thread, int $messageId, int $senderId, int $recipientId, bool $mailerManaged): void {
    $recipient = $this->entityTypeManager->getStorage('user')->load($recipientId);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()
      || !$recipient->hasPermission('use private messaging system')
      || !$recipient->hasPermission('access user profiles')
      || !$thread->access('view', $recipient, TRUE)->isAllowed()
      || $this->isBlockedPair($senderId, $recipientId)) {
      return;
    }

    // Thread access may be restored by a newer message after the recipient
    // cleared their history. Do not notify about an older message that the
    // Private Message module's per-member history filter still hides.
    $lastDeleteTimestamp = $thread->getLastDeleteTimestamp($recipient);
    if (is_numeric($lastDeleteTimestamp)
      && (int) $lastDeleteTimestamp > 0
      && $privateMessage->getCreatedTime() <= (int) $lastDeleteTimestamp) {
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

    if ($notification !== NULL
      && !in_array((string) $notification->get(self::STATUS_FIELD)->value, ['pending', 'failed'], TRUE)) {
      // A retry must not resurrect opt-outs, cancellations, successful sends,
      // or permanent failures after the member's preference later changes.
      return;
    }
    if ($notification !== NULL
      && (bool) $notification->get(self::MAILER_MANAGED_FIELD)->value !== $mailerManaged) {
      // Old queue items stay on Message Notify; new items stay on the Mailer.
      // The marker prevents either worker path from claiming the other's row.
      return;
    }

    // A private conversation always remains available in the site inbox;
    // current confirmation and preference control only supplemental email.
    $emailMode = $this->preferences->emailMode($recipientId, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES);
    if (!filter_var((string) $recipient->getEmail(), FILTER_VALIDATE_EMAIL)
      || !$this->emailPolicy->isConfirmed($recipient)
      || $emailMode === AvisoPreferencias::EMAIL_OFF) {
      if ($notification !== NULL) {
        $notification->set(self::STATUS_FIELD, 'cancelled');
        $notification->set(self::DIGEST_FIELD, 1)->save();
      }
      return;
    }

    if ($emailMode === AvisoPreferencias::EMAIL_DIGEST) {
      if ($notification === NULL) {
        $notification = $this->entityTypeManager->getStorage('message')->create([
          'template' => self::TEMPLATE,
          'uid' => $recipientId,
          self::SOURCE_FIELD => $messageId,
          self::STATUS_FIELD => 'pending',
          self::ATTEMPTS_FIELD => 0,
          self::MAILER_MANAGED_FIELD => $mailerManaged,
          self::DIGEST_FIELD => 0,
        ]);
      }
      else {
        $notification->set(self::STATUS_FIELD, 'pending');
        $notification->set(self::DIGEST_FIELD, 0);
      }
      $notification->save();
      return;
    }

    // Messages are read in the multidomain bar; the notice links to the
    // member's account home.
    $url = $this->domainPurposeManager->routeUrl('account', 'aculta_portal.dashboard');
    if ($url === NULL) {
      if ($notification === NULL) {
        $notification = $this->entityTypeManager->getStorage('message')->create([
          'template' => self::TEMPLATE,
          'uid' => $recipientId,
          self::SOURCE_FIELD => $messageId,
          self::STATUS_FIELD => 'failed',
          self::ATTEMPTS_FIELD => self::MAX_ATTEMPTS,
          self::MAILER_MANAGED_FIELD => $mailerManaged,
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
        self::MAILER_MANAGED_FIELD => $mailerManaged,
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

    if ($mailerManaged) {
      $sender = $this->entityTypeManager->getStorage('user')->load($senderId);
      $senderName = $sender instanceof UserInterface ? $sender->getDisplayName() : 'outro usuário';
      try {
        $result = MessengerMailDelivery::deliver(
          $this->mailer,
          PrivateMessageNoticeMailType::ID,
          (string) $recipient->getEmail(),
          'pt-br',
          [
            'summary' => strtr('Você tem uma nova mensagem de @name.', ['@name' => $senderName]),
            'url' => $url->toString(),
          ],
          'private-message:' . $messageId . ':' . $recipientId . ':' . PrivateMessageNoticeMailType::ID,
        );
      }
      catch (\Throwable $exception) {
        $notification->set(self::STATUS_FIELD, 'failed')->save();
        if ($attempts < self::MAX_ATTEMPTS) {
          throw new \RuntimeException('Private message email delivery failed.', 0, $exception);
        }
        return;
      }
      if ($result->cancelled || $result->sent) {
        $notification->set(self::STATUS_FIELD, $result->sent ? 'sent' : 'cancelled');
        $notification->set(self::DIGEST_FIELD, 1)->save();
        return;
      }
      $notification->set(self::STATUS_FIELD, $result->error === DeliveryError::Permanent ? 'failed_permanent' : 'failed')->save();
      if ($result->error !== DeliveryError::Permanent && $attempts < self::MAX_ATTEMPTS) {
        throw new \RuntimeException('Private message email delivery failed.');
      }
      return;
    }

    if (!$this->messageNotify->send($notification, ['save on success' => FALSE, 'save on fail' => FALSE])) {
      $notification->set(self::STATUS_FIELD, 'failed');
      $notification->save();
      if ($attempts < self::MAX_ATTEMPTS) {
        throw new \RuntimeException('Private message email delivery failed.');
      }
      return;
    }

    $notification->set(self::STATUS_FIELD, 'sent');
    $notification->set(self::DIGEST_FIELD, 1);
    $notification->save();
  }

  /**
   * Checks both block directions using the contrib entity API. */
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
