<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Responde 404 à rota técnica canônica de galerias.
 *
 * A canônica `/media_gallery/{id}` é em inglês e expõe o ID numérico. O
 * público acessa o álbum pelo alias em português (`/albuns/...`). Esta rota
 * não é navegação: não redireciona (sem 301 de slug) e vale para todos,
 * inclusive administradores, que editam pela administração.
 *
 * Decisão R4 (recomendação adotada): ver docs/ARQUITETURA.md, §4.1.
 */
final class CanonicalGalleryRouteSubscriber implements EventSubscriberInterface {

  /**
   * Rota canônica da entidade media_gallery (contrib).
   */
  private const CANONICAL_ROUTE = 'entity.media_gallery.canonical';

  /**
   * Prefixo do caminho técnico cru (sem alias).
   */
  private const TECHNICAL_PREFIX = '/media_gallery/';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Prioridade 30: abaixo do RouterListener (32), que já resolveu a rota.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  /**
   * Bloqueia a rota técnica de galeria.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    // O alias em português resolve para a mesma rota; bloqueia só o caminho técnico cru.
    if ($request->attributes->get('_route') === self::CANONICAL_ROUTE
      && str_starts_with($request->getPathInfo(), self::TECHNICAL_PREFIX)) {
      throw new NotFoundHttpException();
    }
  }

}
