<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Applies a per-client limit before the public ViaCEP proxy makes a request. */
final class CepLookupRateLimitSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly FloodInterface $flood) {}

  public static function getSubscribedEvents(): array {
    // RouterListener resolves the route at priority 32; run before dispatch.
    return [KernelEvents::REQUEST => ['onKernelRequest', 28]];
  }

  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'cep_autocomplete.viacep_lookup') {
      return;
    }

    $identifier = $request->getClientIp() ?: 'unknown';
    $event_name = 'aculta_portal.viacep_lookup';
    if (!$this->flood->isAllowed($event_name, 60, 60, $identifier)) {
      $response = new JsonResponse(['ok' => FALSE, 'message' => 'Lookup temporarily limited'], 429);
      $response->headers->set('Retry-After', '60');
      $response->headers->set('Cache-Control', 'private, no-store');
      $event->setResponse($response);
      return;
    }
    $this->flood->register($event_name, 60, $identifier);
  }

}
