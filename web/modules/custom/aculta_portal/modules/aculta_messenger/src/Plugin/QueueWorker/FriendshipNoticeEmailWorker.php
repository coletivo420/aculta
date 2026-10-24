<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\QueueWorker;

use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_messenger\Service\MessengerEmailLock;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Envia o e-mail mínimo de pedido ou aceite de amizade.
 *
 * Não inclui nome nem conteúdo da amizade e registra a entrega.
 */
#[QueueWorker(
  id: FriendshipNoticeSubscriber::EMAIL_QUEUE,
  title: new TranslatableMarkup('ACULTA Messenger friendship notice email'),
  cron: ['time' => 30],
)]
final class FriendshipNoticeEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const STATUS_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const MAX_ATTEMPTS = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessageNotifyInterface $messageNotify,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly LockBackendInterface $lock,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('message_notify.sender'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('lock.persistent'),
    );
  }

  /**
   * Processa um aviso pelo ID; o destinatário e o estado são relidos.
   */
  public function processItem($data): void {
    $messageId = is_array($data) ? filter_var($data['message_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($messageId === FALSE || $messageId < 1) {
      return;
    }
    $lockId = 'aculta_messenger:friendship_notice_email:' . $messageId;
    if (!$this->lock->acquire($lockId, MessengerEmailLock::TIMEOUT)) {
      throw new \RuntimeException('Friendship notice email is already being processed.');
    }
    try {
      // Reload only after locking. Duplicate queue items must observe the
      // persisted status and retry count written by the active worker.
      $message = $this->entityTypeManager->getStorage('message')->load($messageId);
      if (!$message instanceof MessageInterface || $message->bundle() !== FriendshipNoticeSubscriber::TEMPLATE
        || $message->get(self::STATUS_FIELD)->value === 'sent') {
        return;
      }

      $recipient = $this->entityTypeManager->getStorage('user')->load($message->getOwnerId());
      $url = $this->domainPurposeManager->routeUrl('account', 'aculta_portal.dashboard');
      if ($recipient === NULL || !$recipient->isActive() || !is_string($recipient->getEmail()) || $recipient->getEmail() === '' || $url === NULL) {
        $message->set(self::STATUS_FIELD, 'failed')->save();
        return;
      }

      $attempts = (int) $message->get(self::ATTEMPTS_FIELD)->value;
      if ($attempts >= self::MAX_ATTEMPTS) {
        $message->set(self::STATUS_FIELD, 'failed')->save();
        return;
      }
      $attempts++;
      $message->setArguments(['@account_url' => $url->toString()]);
      $message->set(self::ATTEMPTS_FIELD, $attempts)->save();

      if (!$this->messageNotify->send($message, ['save on success' => FALSE, 'save on fail' => FALSE])) {
        $message->set(self::STATUS_FIELD, 'failed')->save();
        if ($attempts < self::MAX_ATTEMPTS) {
          throw new \RuntimeException('Friendship notice email delivery failed.');
        }
        return;
      }
      $message->set(self::STATUS_FIELD, 'sent')->save();
    }
    finally {
      $this->lock->release($lockId);
    }
  }

}
