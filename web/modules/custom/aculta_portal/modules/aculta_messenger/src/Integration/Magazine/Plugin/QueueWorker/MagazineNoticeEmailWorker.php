<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Magazine\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Magazine\Mail\MagazineNoticeMailType;
use Drupal\aculta_messenger\Integration\Magazine\MagazineNoticeManagerInterface;
use Drupal\aculta_messenger\Service\MessengerEmailLock;
use Drupal\aculta_messenger\Service\MessengerMailDelivery;
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
 * Delivers direct Magazine emails after revalidating preference and access. */
#[QueueWorker(
  id: 'aculta_magazine_messenger_email',
  title: new TranslatableMarkup('ACULTA Magazine notice email'),
  cron: ['time' => 60],
)]
final class MagazineNoticeEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const MAX_ATTEMPTS = 5;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MagazineNoticeManagerInterface $notices,
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
      $container->get('aculta_messenger.magazine_notice_manager'),
      $container->get('aculta_messenger.aviso_preferencias'),
      $container->get('aculta_portal.email_confirmation_policy'),
      $container->get('aculta_mailer.mailer'),
      $container->get('lock.persistent'),
    );
  }

  /**
   * Processes a single direct email queue item. */
  public function processItem($data): void {
    $id = is_array($data) ? filter_var($data['message_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($id === FALSE || $id < 1) {
      return;
    }
    $key = 'aculta_magazine_email:' . $id;
    if (!$this->lock->acquire($key, MessengerEmailLock::TIMEOUT)) {
      throw new \RuntimeException('Magazine notice email is currently being processed.');
    }
    try {
      // Reload after acquiring the lock to avoid delivering a stale duplicate.
      $message = $this->entityTypeManager->getStorage('message')->loadUnchanged($id);
      if (!$message instanceof MessageInterface || $message->bundle() !== 'aculta_msg_magazine_notice'
        || !in_array($message->get('field_aculta_msg_delivery')->value, ['pending', 'failed'], TRUE)) {
        return;
      }
      if ((int) $message->get('field_aculta_messenger_attempts')->value >= self::MAX_ATTEMPTS) {
        $this->finish($message, 'failed_permanent');
        return;
      }
      $this->send($message);
    }
    finally {
      $this->lock->release($key);
    }
  }

  /**
   * Revalidates user, mode, source access, and administrative Mailer policy. */
  private function send(MessageInterface $message): void {
    $uid = (int) $message->getOwnerId();
    $user = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$user instanceof UserInterface || !$user->isActive()) {
      $this->finish($message, 'cancelled');
      return;
    }
    if (!filter_var((string) $user->getEmail(), FILTER_VALIDATE_EMAIL)) {
      $this->finish($message, 'cancelled');
      return;
    }
    if (!$this->emailPolicy->isConfirmed($user)) {
      $this->finish($message, 'cancelled');
      return;
    }
    if ($this->preferences->emailMode($uid, 'revista') !== AvisoPreferencias::EMAIL_IMMEDIATE) {
      $this->finish($message, 'cancelled');
      return;
    }
    $notice = $this->notices->resolve($message, $user);
    if ($notice === NULL) {
      $this->finish($message, 'cancelled');
      return;
    }
    $attempts = (int) $message->get('field_aculta_messenger_attempts')->value + 1;
    $message->set('field_aculta_messenger_attempts', $attempts)
      ->set('field_aculta_msg_delivery', 'pending')->save();
    try {
      $result = MessengerMailDelivery::deliver(
        $this->mailer,
        MagazineNoticeMailType::ID,
        (string) $user->getEmail(),
        'pt-br',
        ['summary' => (string) $notice['email_summary'], 'url' => $notice['url']->toString()],
        $message->bundle() . ':' . $message->uuid() . ':' . MagazineNoticeMailType::ID,
        (string) $message->uuid(),
      );
    }
    catch (\Throwable) {
      $this->finish($message, $attempts >= self::MAX_ATTEMPTS ? 'failed_permanent' : 'failed');
      if ($attempts < self::MAX_ATTEMPTS) {
        throw new \RuntimeException('Magazine notice email delivery failed.');
      }
      return;
    }
    if ($result->cancelled || $result->sent) {
      $this->finish($message, $result->sent ? 'sent' : 'cancelled');
      return;
    }
    if ($result->error === DeliveryError::Permanent) {
      $this->finish($message, 'failed_permanent');
      return;
    }
    $this->finish($message, $attempts >= self::MAX_ATTEMPTS ? 'failed_permanent' : 'failed');
    if ($attempts < self::MAX_ATTEMPTS) {
      throw new \RuntimeException('Magazine notice email delivery failed.');
    }
  }

  /**
   * Stores a final delivery state without touching the mandatory site notice. */
  private function finish(MessageInterface $message, string $state): void {
    $message->set('field_aculta_msg_delivery', $state)->save();
  }

}
