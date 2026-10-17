<?php

declare(strict_types=1);

namespace Drupal\aculta_user\EventSubscriber;

use Drupal\aculta_portal\Routing\PublicRouteSlug;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Drupal\Core\Routing\RouteProviderInterface;

/**
 * Canonicaliza o slug do perfil público para minúsculas antes do roteamento.
 *
 * A regra de minúsculas vale só para o segmento de URL (ver PublicRouteSlug). Uma requisição com maiúsculas não
 * casa com a rota pública e, sem este redirecionamento, falha no pipeline do Drupal (500). Responde 301 para a
 * forma canônica, no mesmo host. O padrão de caminho vem da própria rota aculta_user.perfil_publico. Prioridade
 * acima do RouterListener (32), para não chegar ao roteador.
 */
final class PublicProfileSlugRedirectSubscriber implements EventSubscriberInterface {

  private const ROTA = 'aculta_user.perfil_publico';

  public function __construct(private readonly RouteProviderInterface $routes) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['redirecionar', 40]]];
  }

  public function redirecionar(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    try {
      $modelo = $this->routes->getRouteByName(self::ROTA)->getPath();
    }
    catch (RouteNotFoundException) {
      return;
    }
    $request = $event->getRequest();
    $padrao = '#^' . str_replace('\{apelido\}', '([^/]+)', preg_quote($modelo, '#')) . '$#u';
    if (!preg_match($padrao, $request->getPathInfo(), $m)) {
      return;
    }
    $canonico = PublicRouteSlug::normalize($m[1]);
    if ($canonico === $m[1]) {
      return;
    }
    $url = $request->getSchemeAndHttpHost() . str_replace('{apelido}', rawurlencode($canonico), $modelo);
    $query = $request->getQueryString();
    if ($query !== NULL && $query !== '') {
      $url .= '?' . $query;
    }
    $event->setResponse(new RedirectResponse($url, 301));
  }

}
