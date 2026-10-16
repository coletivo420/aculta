<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ler o tópico zera o contador do aviso do site (ACULTA Fórum, F5.2).
 */
final class ForumAvisoReadSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly AvisoService $avisos,
  ) {}

  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'entity.node.canonical' || $this->currentUser->isAnonymous()) {
      return;
    }
    $nid = $request->attributes->get('node');
    if (is_numeric($nid)) {
      $this->avisos->marcarLido((int) $this->currentUser->id(), (int) $nid);
    }
  }

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 0]];
  }

}
