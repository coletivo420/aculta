<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManager;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\aculta_messenger\Service\MessengerEmailLock;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delivers direct Donation notice e-mail through ACULTA Mailer. */
#[QueueWorker(
  id: DonationNoticeManager::DIRECT_EMAIL_QUEUE,
  title: new TranslatableMarkup('ACULTA Donation notice email'),
  cron: ['time' => 30],
)]
final class DonationNoticeEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const MAX_ATTEMPTS = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DonationNoticeManagerInterface $notices,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly EmailConfirmationPolicy $emailPolicy,
    private readonly AcultaMailerInterface $mailer,
    private readonly LockBackendInterface $lock,
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
      $container->get('aculta_messenger.donation_notice_manager'),
      $container->get('aculta_messenger.aviso_preferencias'),
      $container->get('aculta_portal.email_confirmation_policy'),
      $container->get('aculta_mailer.mailer'),
      $container->get('lock.persistent'),
    );
  }

  /**
   * {@inheritdoc} */
  public function processItem($data): void {
    $id = is_array($data) ? filter_var($data['message_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($id === FALSE || $id < 1) {
      return;
    }
    $lockId = 'aculta_messenger:donation_email:' . $id;
    if (!$this->lock->acquire($lockId, MessengerEmailLock::TIMEOUT)) {
      throw new \RuntimeException('Donation notice email is already being processed.');
    }
    try {
      $message = $this->entityTypeManager->getStorage('message')->load((int) $id);
      if (!$message instanceof MessageInterface || $message->bundle() !== DonationNoticeManager::TEMPLATE
        || $message->get('field_aculta_msg_delivery')->value !== 'pending') {
        return;
      }
      $currentAttempts = (int) $message->get('field_aculta_messenger_attempts')->value;
      if ($currentAttempts >= self::MAX_ATTEMPTS) {
        $message->set('field_aculta_msg_delivery', 'failed')->save();
        return;
      }
      $attempts = $currentAttempts + 1;
      $message->set('field_aculta_messenger_attempts', $attempts)->save();
      $uid = (int) $message->getOwnerId();
      $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
      $notice = $recipient instanceof UserInterface ? $this->notices->resolve($message, $recipient) : NULL;
      if (!$recipient instanceof UserInterface || !$recipient->isActive()
        || !filter_var((string) $recipient->getEmail(), FILTER_VALIDATE_EMAIL)
        || !$this->emailPolicy->isConfirmed($recipient)
        || $this->preferences->emailMode($uid, 'doacao') !== AvisoPreferencias::EMAIL_IMMEDIATE
        || $notice === NULL) {
        $message->set('field_aculta_msg_delivery', 'cancelled')->save();
        return;
      }

      try {
        $result = $this->mailer->deliver(new MailMessage(
          'doacao_aviso_pessoal',
          (string) $recipient->getEmail(),
          'pt-br',
          ['summary' => $notice['email_summary'], 'url' => $notice['url']->toString()],
          (string) $message->uuid(),
        ));
      }
      catch (\Throwable) {
        $message->set('field_aculta_msg_delivery', $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending')->save();
        if ($attempts < self::MAX_ATTEMPTS) {
          throw new \RuntimeException('Donation notice e-mail delivery failed.');
        }
        return;
      }
      if ($result->sent) {
        $message->set('field_aculta_msg_delivery', 'sent')->save();
      }
      elseif ($result->cancelled || $result->error === DeliveryError::Permanent || $attempts >= self::MAX_ATTEMPTS) {
        $message->set('field_aculta_msg_delivery', $result->cancelled ? 'cancelled' : 'failed')->save();
      }
      else {
        $message->set('field_aculta_msg_delivery', 'pending')->save();
        throw new \RuntimeException('Donation notice e-mail delivery failed.');
      }
    }
    finally {
      $this->lock->release($lockId);
    }
  }

}
