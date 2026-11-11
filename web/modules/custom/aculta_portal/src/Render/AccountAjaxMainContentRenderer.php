<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Render;

use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decora o renderizador AJAX nas rotas da Minha conta.
 *
 * O título da página entra no próprio conteúdo como bloco oculto, para a
 * navegação atualizar o título depois da troca. A trilha de navegação não é
 * montada aqui: ela vem somente do bloco de breadcrumb do layout de blocos.
 */
final class AccountAjaxMainContentRenderer implements MainContentRendererInterface {

  public function __construct(
    private readonly MainContentRendererInterface $inner,
    private readonly TitleResolverInterface $titleResolver,
  ) {}

  /**
   * {@inheritdoc} */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if ($route !== NULL && $route->getOption('_aculta_site_key') === 'account') {
      $title = (string) $this->titleResolver->getTitle($request, $route);
      // Fragmento oculto que account-navigation.js lê para atualizar o título.
      $main_content['portal_account_chrome'] = [
        '#type' => 'container',
        '#attributes' => ['hidden' => 'hidden', 'data-portal-chrome' => '', 'data-portal-page-title' => $title],
        '#weight' => -1000,
      ];
    }
    return $this->inner->renderResponse($main_content, $request, $route_match);
  }

}
