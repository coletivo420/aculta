<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_messenger\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\message\MessageInterface;
use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends minimum enrollment and certificate notices through ACULTA Mailer.
 */
#[QueueWorker(
  id: 'aculta_lms_messenger_email',
  title: new TranslatableMarkup('ACULTA LMS transactional notification'),
  cron: ['time' => 30],
)]
final class LmsNotificationEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const MAX_ATTEMPTS = 3;

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LearningNoticeManagerInterface $notices,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly AcultaMailerInterface $mailer,
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
      $container->get('aculta_lms_messenger.notice_manager'),
      $container->get('aculta_messenger.aviso_preferencias'),
      $container->get('aculta_mailer.mailer'),
      $container->get('lock.persistent'),
    );
  }

  /**
   * Processes only a Message ID; all recipient and source state is reloaded.
   */
  public function processItem($data): void {
    if (!is_array($data)) {
      return;
    }
    $messageId = filter_var($data['message_id'] ?? NULL, FILTER_VALIDATE_INT);
    if ($messageId === FALSE || $messageId < 1) {
      return;
    }
    $message = $this->entityTypeManager->getStorage('message')->load($messageId);
    if (!$message instanceof MessageInterface || $message->bundle() !== 'aculta_msg_lms_notice'
      || $message->get(self::DELIVERY_FIELD)->value === 'sent') {
      return;
    }

    $lockId = 'aculta_lms_messenger:message:' . $messageId;
    if (!$this->lock->acquire($lockId, 60.0)) {
      throw new \RuntimeException('LMS notification is already being processed.');
    }
    try {
      $this->send($message);
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * Revalidates current ownership/access before sending a generic email.
   */
  private function send(MessageInterface $message): void {
    $recipient = $this->entityTypeManager->getStorage('user')->load($message->getOwnerId());
    if (!$recipient instanceof UserInterface || !$recipient->isActive() || !is_string($recipient->getEmail()) || $recipient->getEmail() === '') {
      $message->set(self::DELIVERY_FIELD, 'failed')
        ->set(self::ATTEMPTS_FIELD, self::MAX_ATTEMPTS)
        ->save();
      return;
    }
    if ($this->preferences->emailMode((int) $recipient->id(), 'aprendizagem') !== AvisoPreferencias::EMAIL_IMMEDIATE) {
      $message->set(self::DELIVERY_FIELD, 'cancelled')->save();
      return;
    }
    $notice = $this->notices->resolve($message, $recipient);
    if ($notice === NULL) {
      // A removed membership suppresses a queued enrollment email. Certificate
      // link stays valid by snapshot after course/account changes.
      $message->set(self::DELIVERY_FIELD, 'failed')->save();
      return;
    }
    $attempts = (int) $message->get(self::ATTEMPTS_FIELD)->value;
    if ($attempts >= self::MAX_ATTEMPTS) {
      $message->set(self::DELIVERY_FIELD, 'failed')->save();
      return;
    }
    $attempts++;
    $message->set(self::ATTEMPTS_FIELD, $attempts)->set(self::DELIVERY_FIELD, 'pending')->save();
    $mail = new MailMessage(
      'lms_transactional_notice',
      (string) $recipient->getEmail(),
      'pt-br',
      [
        'summary' => (string) $notice['email_summary'],
        'url' => $notice['url']->toString(),
      ],
      (string) $message->uuid(),
    );
    $result = $this->mailer->deliver($mail);
    if ($result->cancelled) {
      $message->set(self::DELIVERY_FIELD, 'cancelled')->save();
      return;
    }
    if (!$result->sent) {
      $message->set(self::DELIVERY_FIELD, 'failed')->save();
      if ($attempts < self::MAX_ATTEMPTS) {
        throw new \RuntimeException('LMS transactional notification email delivery failed.');
      }
      return;
    }
    $message->set(self::DELIVERY_FIELD, 'sent')->save();
  }

}
