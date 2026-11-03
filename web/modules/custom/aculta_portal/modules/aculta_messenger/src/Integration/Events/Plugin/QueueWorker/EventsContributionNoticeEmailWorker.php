<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Events\EventsContributionNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Events\Mail\EventsContributionNoticeMailType;
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
 * Delivers direct outcome emails after rechecking access and preferences. */
#[QueueWorker(
  id: 'aculta_events_contribution_messenger_email',
  title: new TranslatableMarkup('ACULTA Events contribution notice email'),
  cron: ['time' => 60],
)]
final class EventsContributionNoticeEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const MAX_ATTEMPTS = 5;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventsContributionNoticeManagerInterface $notices,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly EmailConfirmationPolicy $emailPolicy,
    private readonly AcultaMailerInterface $mailer,
    private readonly LockBackendInterface $lock,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates the worker from Drupal services.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'), $container->get('aculta_messenger.events_contribution_notice_manager'),
      $container->get('aculta_messenger.aviso_preferencias'), $container->get('aculta_portal.email_confirmation_policy'),
      $container->get('aculta_mailer.mailer'), $container->get('lock'));
  }

  /**
   * Processes a single direct e-mail queue item.
   */
  public function processItem($data): void {
    $id = is_array($data) ? filter_var($data['message_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($id === FALSE || $id < 1) {
      return;
    }
    $key = 'aculta_events_contribution_email:' . $id;
    if (!$this->lock->acquire($key, MessengerEmailLock::TIMEOUT)) {
      throw new \RuntimeException('Events contribution email is currently being processed.');
    }
    try {
      $message = $this->entityTypeManager->getStorage('message')->loadUnchanged($id);
      if (!$message instanceof MessageInterface || $message->bundle() !== 'aculta_msg_events_contrib_notice'
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
   * Revalidates current user preference and proposal access before sending.
   */
  private function send(MessageInterface $message): void {
    $uid = (int) $message->getOwnerId();
    $user = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$user instanceof UserInterface || !$user->isActive() || !filter_var((string) $user->getEmail(), FILTER_VALIDATE_EMAIL)
      || !$this->emailPolicy->isConfirmed($user)
      || $this->preferences->emailMode($uid, AvisoPreferencias::EMAIL_EVENTS_CONTRIBUTIONS) !== AvisoPreferencias::EMAIL_IMMEDIATE) {
      $this->finish($message, 'cancelled');
      return;
    }
    $notice = $this->notices->resolve($message, $user);
    if ($notice === NULL) {
      $this->finish($message, 'cancelled');
      return;
    }
    $attempts = (int) $message->get('field_aculta_messenger_attempts')->value + 1;
    $message->set('field_aculta_messenger_attempts', $attempts)->set('field_aculta_msg_delivery', 'pending')->save();
    try {
      $result = MessengerMailDelivery::deliver(
        $this->mailer,
        EventsContributionNoticeMailType::ID,
        (string) $user->getEmail(),
        'pt-br',
        ['summary' => $notice['email_summary'], 'url' => $notice['url']->toString()],
        $message->bundle() . ':' . $message->uuid() . ':' . EventsContributionNoticeMailType::ID, (string) $message->uuid());
    }
    catch (\Throwable) {
      $this->finish($message, $attempts >= self::MAX_ATTEMPTS ? 'failed_permanent' : 'failed');
      if ($attempts < self::MAX_ATTEMPTS) {
        throw new \RuntimeException('Events contribution notice email delivery failed.');
      }
      return;
    }
    if ($result->cancelled || $result->sent) {
      $this->finish($message, $result->sent ? 'sent' : 'cancelled');
      return;
    }
    $terminal = $result->error === DeliveryError::Permanent || $attempts >= self::MAX_ATTEMPTS;
    $this->finish($message, $terminal ? 'failed_permanent' : 'failed');
    if (!$terminal) {
      throw new \RuntimeException('Events contribution notice email delivery failed.');
    }
  }

  /**
   * Persists a terminal or retryable mail state.
   */
  private function finish(MessageInterface $message, string $state): void {
    $message->set('field_aculta_msg_delivery', $state)->save();
  }

}
