<?php

declare(strict_types=1);

namespace Drupal\aculta_secrets_manager\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Impede que respostas do painel de credenciais sejam guardadas por cache HTTP ou intermediários.
 */
final class SecretsResponseSubscriber implements EventSubscriberInterface {

  /** Rotas do painel e dos popups de credenciais. */
  public const ROUTES = [
    'aculta_portal.secrets_import',
    'aculta_portal.secrets_show',
    'aculta_portal.secrets_edit',
    'aculta_portal.secrets_clear',
  ];

  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $route = (string) $event->getRequest()->attributes->get('_route');
    if (!in_array($route, self::ROUTES, TRUE)) {
      return;
    }
    $headers = $event->getResponse()->headers;
    $headers->set('Cache-Control', 'no-store, private, max-age=0');
    $headers->set('Pragma', 'no-cache');
  }

}
