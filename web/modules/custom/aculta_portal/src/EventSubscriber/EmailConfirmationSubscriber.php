<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\Event\UserEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Login por OAuth confirma o e-mail do usuário (o provedor já verificou o endereço).
 */
final class EmailConfirmationSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly EmailConfirmationPolicy $policy) {}

  public static function getSubscribedEvents(): array {
    return [
      SocialAuthEvents::USER_CREATED => 'markConfirmed',
      SocialAuthEvents::USER_LOGIN => 'markConfirmed',
    ];
  }

  public function markConfirmed(UserEvent $event): void {
    $this->policy->markConfirmed((int) $event->getUser()->id());
  }

}
