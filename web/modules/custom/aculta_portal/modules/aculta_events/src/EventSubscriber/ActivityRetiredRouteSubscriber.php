<?php

declare(strict_types=1);

namespace Drupal\aculta_events\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Aposenta rotas de atividades legadas (/node/add/activity) com HTTP 404.
 *
 * Somente Eventos são mantidos; a criação ocorre em /painel-administrativo/eventos/adicionar.
 */
final class ActivityRetiredRouteSubscriber implements EventSubscriberInterface {

  /**
   * Intercepta acessos a /node/add/activity e retorna 404.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $path = $event->getRequest()->getPathInfo();
    if ($path === '/node/add/activity' || str_starts_with($path, '/node/add/activity/')) {
      throw new NotFoundHttpException('Atividades foram descontinuadas em favor de Eventos. Utilize /painel-administrativo/eventos/adicionar.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 50],
    ];
  }

}
