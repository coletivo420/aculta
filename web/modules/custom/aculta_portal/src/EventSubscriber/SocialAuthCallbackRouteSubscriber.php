<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Retorno OAuth das redes sociais no padrão de integrações do projeto: `/integracoes/<nome>/retorno`.
 *
 * O `social_auth` gera a URL de retorno a partir da rota `social_auth.network.callback`. Trocar o caminho dessa rota
 * faz o Google (e as demais redes) passar a receber `/integracoes/google/retorno`. Requisito: cadastrar a nova URL no
 * console do provedor. Enquanto o console mantiver a URL antiga, o login com essa rede falha.
 */
final class SocialAuthCallbackRouteSubscriber extends RouteSubscriberBase {

  /** Caminho do retorno OAuth das redes sociais, no padrão de integrações. */
  public const CALLBACK_PATH = '/integracoes/{network}/retorno';

  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('social_auth.network.callback');
    if ($route !== NULL) {
      $route->setPath(self::CALLBACK_PATH);
    }
  }

}
