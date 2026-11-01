<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\social_auth\Event\LoginEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Stores the Google account email needed by the private Connections page. */
final class SocialAuthProfileSubscriber implements EventSubscriberInterface {

  private const GOOGLE_PLUGIN_ID = 'social_auth_google';

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function getSubscribedEvents(): array {
    return [SocialAuthEvents::USER_LOGIN => 'onUserLogin'];
  }

  public function onUserLogin(LoginEvent $event): void {
    if ($event->getPluginId() !== self::GOOGLE_PLUGIN_ID) {
      return;
    }

    $email = $event->getSocialAuthUser()->getEmail();
    if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      return;
    }

    $providerId = (string) $event->getSocialAuthUser()->getId();
    if ($providerId === '') {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('social_auth');
    $records = $storage->loadByProperties([
      'user_id' => $event->getDrupalAccount()->id(),
      'plugin_id' => self::GOOGLE_PLUGIN_ID,
      'provider_user_id' => $providerId,
    ]);

    foreach ($records as $record) {
      $additionalData = $record->getAdditionalData();
      if (($additionalData['provider_email'] ?? NULL) === $email) {
        continue;
      }

      $additionalData['provider_email'] = $email;
      $record->setAdditionalData($additionalData);
      $record->save();
    }
  }

}
