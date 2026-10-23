<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_commerce\Navigation\GreenStashNavigation;
use Drupal\aculta_commerce\Catalog\GreenStashProductReader;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrypoint for the independent Green Stash Studio experience.
 */
final class GreenStashController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
    private readonly GreenStashNavigation $navigation,
    private readonly GreenStashProductReader $products,
    private readonly RequestStack $requests,
  ) {}

  public function page(string $secao = 'visao-geral'): array {
    $provider = $this->experiences->get('green_stash');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('green_stash', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    if (!isset(GreenStashNavigation::SLUGS[$secao])) {
      throw new NotFoundHttpException();
    }
    $build = $this->navigation->build($secao);
    if ($secao === 'produtos') {
      $build['notice']['#value'] = 'Produtos da loja que você pode administrar.';
      $request = $this->requests->getCurrentRequest();
      $before = $request?->query->get('antes', '0') ?? '0';
      $before = is_string($before) && preg_match('/^[1-9][0-9]{0,17}$/', $before) ? (int) $before : 0;
      $search = $request?->query->get('busca', '') ?? '';
      $search = is_string($search) ? mb_substr(trim($search), 0, 100) : '';
      $build['search'] = [
        '#type' => 'container',
        'form' => [
          '#type' => 'inline_template',
          '#template' => '<form method="get" action="{{ action }}"><label for="green-stash-busca">Buscar produtos</label><input id="green-stash-busca" name="busca" type="search" maxlength="100" value="{{ busca }}"/><button type="submit">Buscar</button></form>',
          '#context' => ['action' => Url::fromRoute('aculta_commerce.green_stash_area', ['secao' => 'produtos'])->toString(), 'busca' => $search],
        ],
      ];
      $build['catalog'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'green-stash-products'],
        '#cache' => ['max-age' => 0],
      ];
      $page = $this->products->page($this->account, $before, $search);
      $products = $page['items'];
      if ($products === []) {
        $build['catalog']['empty'] = [
          '#markup' => 'Nenhum produto administrável encontrado.',
        ];
      }
      else {
        foreach ($products as $product) {
          $build['catalog']['item_' . $product->id()] = [
            '#type' => 'container',
            'name' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $product->label(),
            ],
            'detail' => [
              '#type' => 'link',
              '#title' => 'Ver detalhes',
              '#url' => Url::fromRoute('aculta_commerce.green_stash_product_detail', ['produto' => $product->id()]),
            ],
          ];
        }
      }
      if ($page['next'] !== NULL) {
        $build['catalog']['next'] = [
          '#type' => 'link',
          '#title' => 'Próxima página',
          '#url' => Url::fromRoute('aculta_commerce.green_stash_area', ['secao' => 'produtos'], ['query' => ['antes' => $page['next'], 'busca' => $search]]),
        ];
      }
    }
    return $build;
  }

}
