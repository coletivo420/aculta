<?php

declare(strict_types=1);

namespace Drupal\aculta_user\EventSubscriber;

use Drupal\aculta_portal\Routing\PublicRouteSlug;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Slug de perfil público fora do canônico (ex.: letras maiúsculas) responde 404, sem redirecionamento.
 *
 * O slug publicado é estável e não há redirecionamento de slug (AGENTS.md). Uma requisição com maiúsculas não
 * casa com a rota pública. O redirect (contrib) tenta normalizar caminhos sem rota, gera a rota com o valor
 * original e falha (500). Esta classe marca `_disable_route_normalizer` antes do normalizador (prioridade 31,
 * entre o roteador 32 e o normalizador 30); o roteador então responde 404 normalmente. Vale também para sub-requisições
 * (a página 404 é renderizada em uma sub-requisição, que recebe um Request novo sem a flag da principal).
 */
final class PublicProfileNotCanonicalSubscriber implements EventSubscriberInterface {

  private const ROTA = 'aculta_user.perfil_publico';

  public function __construct(private readonly RouteProviderInterface $routes) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['desativarNormalizador', 31]]];
  }

  public function desativarNormalizador(RequestEvent $event): void {
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
    if (PublicRouteSlug::normalize($m[1]) !== $m[1]) {
      // O roteador casa o padrão sem diferenciar maiúsculas. Sem a rota, o metatag e o normalizador não geram URL
      // com o apelido bruto (500); a resposta é o 404 normal.
      $request->attributes->set('_disable_route_normalizer', TRUE);
      foreach (['_route', '_route_params', '_controller'] as $atributo) {
        $request->attributes->remove($atributo);
      }
      throw new NotFoundHttpException();
    }
  }

}
