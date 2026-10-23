<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_commerce\Navigation\GreenStashNavigation;
use Drupal\aculta_commerce\Catalog\GreenStashProductReader;
use Drupal\Core\Session\AccountInterface;
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
      $build['notice']['#value'] = 'Produtos da loja que você pode administrar. A lista é limitada nesta fase.';
      $build['catalog'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'green-stash-products'],
        '#cache' => ['max-age' => 0],
      ];
      $products = $this->products->listEditable($this->account);
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
            'edit' => [
              '#type' => 'link',
              '#title' => 'Editar produto',
              '#url' => $product->toUrl('edit-form'),
            ],
          ];
        }
      }
    }
    return $build;
  }

}
