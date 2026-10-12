<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Render;

use Drupal\Component\Utility\Html;
use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorador do renderizador AJAX de conteúdo principal, para rotas da Minha conta.
 *
 * O título e o breadcrumb são calculados pelo servidor (os mesmos da página direta) e entram no próprio conteúdo
 * como bloco oculto, à frente. A navegação os lê depois da troca: o cliente não tira título do conteúdo.
 */
final class AccountAjaxMainContentRenderer implements MainContentRendererInterface {

  public function __construct(
    private readonly MainContentRendererInterface $inner,
    private readonly TitleResolverInterface $titleResolver,
    private readonly BreadcrumbManager $breadcrumbManager,
  ) {}

  /** {@inheritdoc} */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if ($route !== NULL && $route->getOption('_aculta_domain_purpose') === 'account') {
      $title = (string) $this->titleResolver->getTitle($request, $route);
      $items = [];
      $links = $this->breadcrumbManager->build($route_match)->getLinks();
      $last = count($links) - 1;
      foreach ($links as $index => $link) {
        $text = Html::escape((string) $link->getText());
        if ($index === $last || !$link->getUrl()) {
          $items[] = '<li class="breadcrumb-item active" aria-current="page">' . $text . '</li>';
        }
        else {
          $items[] = '<li class="breadcrumb-item"><a href="' . Html::escape($link->getUrl()->toString()) . '">' . $text . '</a></li>';
        }
      }
      $markup = '<div hidden data-portal-chrome data-portal-page-title="' . Html::escape($title) . '"><ol>' . implode('', $items) . '</ol></div>';
      $main_content['portal_account_chrome'] = ['#markup' => $markup, '#weight' => -1000];
    }
    return $this->inner->renderResponse($main_content, $request, $route_match);
  }

}
