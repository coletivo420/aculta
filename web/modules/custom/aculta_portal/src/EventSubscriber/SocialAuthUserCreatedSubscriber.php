<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\Event\UserEvent;
use Drupal\user\UserDataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records that a Social Auth-created account has no user-chosen local password. */
final class SocialAuthUserCreatedSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly UserDataInterface $userData) {}

  public static function getSubscribedEvents(): array {
    return [SocialAuthEvents::USER_CREATED => 'onUserCreated'];
  }

  public function onUserCreated(UserEvent $event): void {
    $this->userData->set('aculta_portal', $event->getUser()->id(), 'social_auth_password_unset', TRUE);
  }

}
