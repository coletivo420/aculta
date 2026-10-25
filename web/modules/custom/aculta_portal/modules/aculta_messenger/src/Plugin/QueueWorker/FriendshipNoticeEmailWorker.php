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
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_messenger\Mail\FriendshipNoticeMailType;
use Drupal\aculta_messenger\Service\MessengerMailDelivery;
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

  private const MAILER_MANAGED_FIELD = 'field_aculta_mailer_managed';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessageNotifyInterface $messageNotify,
    private readonly AcultaMailerInterface $mailer,
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
      $container->get('aculta_mailer.mailer'),
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
      $mailerManaged = ($data['mailer_managed'] ?? FALSE) === TRUE;
      if ((bool) $message->get(self::MAILER_MANAGED_FIELD)->value !== $mailerManaged) {
        // Keep each queue generation on its original transport.
        return;
      }

      $recipient = $this->entityTypeManager->getStorage('user')->load($message->getOwnerId());
      $url = $this->domainPurposeManager->routeUrl('account', 'aculta_portal.dashboard');
      if ($recipient === NULL || !$recipient->isActive()
        || !is_string($recipient->getEmail()) || $recipient->getEmail() === '' || $url === NULL) {
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

      if ($mailerManaged) {
        $event = (string) $message->get(FriendshipNoticeSubscriber::EVENT_FIELD)->value;
        $summary = $event === 'accepted'
          ? 'Seu pedido de amizade foi aceito.'
          : 'Você recebeu um pedido de amizade.';
        try {
          $result = MessengerMailDelivery::deliver(
            $this->mailer,
            FriendshipNoticeMailType::ID,
            (string) $recipient->getEmail(),
            'pt-br',
            ['summary' => $summary, 'url' => $url->toString()],
            'friendship-notice:' . $messageId . ':' . FriendshipNoticeMailType::ID,
          );
        }
        catch (\Throwable) {
          $message->set(self::STATUS_FIELD, 'failed')->save();
          if ($attempts < self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Friendship notice email delivery failed.');
          }
          return;
        }
        if ($result->sent || $result->cancelled) {
          $message->set(self::STATUS_FIELD, $result->sent ? 'sent' : 'cancelled')->save();
          return;
        }
        $message->set(self::STATUS_FIELD, $result->error === DeliveryError::Permanent ? 'failed_permanent' : 'failed')->save();
        if ($result->error !== DeliveryError::Permanent && $attempts < self::MAX_ATTEMPTS) {
          throw new \RuntimeException('Friendship notice email delivery failed.');
        }
        return;
      }

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
