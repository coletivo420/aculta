<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\Event\UserEvent;
use Drupal\user\UserDataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records that a Social Auth-created account has no user-chosen local password. */
final class SocialAuthUserCreatedSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly UserDataInterface $userData) {}

  /**
   * Declara os eventos assinados pelo subscriber.
   *
   * @return array
   *   Mapa de eventos para os métodos tratadores.
   */
  public static function getSubscribedEvents(): array {
    return [SocialAuthEvents::USER_CREATED => 'onUserCreated'];
  }

  /**
   * Marca a conta criada pelo Social Auth como sem senha local.
   *
   * @param \Drupal\social_auth\Event\UserEvent $event
   *   Evento de usuário criado pelo Social Auth.
   */
  public function onUserCreated(UserEvent $event): void {
    $this->userData->set('aculta_portal', $event->getUser()->id(), 'social_auth_password_unset', TRUE);
  }

}
