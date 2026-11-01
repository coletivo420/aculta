<?php

declare(strict_types=1);

namespace Drupal\aculta_secrets_manager\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Prevents credential panel responses from being cached by HTTP intermediaries.
 */
final class SecretsResponseSubscriber implements EventSubscriberInterface {

  /**
   * Credential panel and popup route names.
   *
   * @var string[]
   */
  public const ROUTES = [
    'aculta_portal.secrets_import',
    'aculta_portal.secrets_show',
    'aculta_portal.secrets_edit',
    'aculta_portal.secrets_clear',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  /**
   * Sets no-store headers on credential panel responses.
   */
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
