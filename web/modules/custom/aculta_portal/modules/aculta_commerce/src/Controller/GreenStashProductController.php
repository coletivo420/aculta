<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Controller;

use Drupal\aculta_commerce\Catalog\GreenStashProductReader;
use Drupal\aculta_commerce\Navigation\GreenStashNavigation;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Product detail inside Studio, without Commerce administrative form routes.
 */
final class GreenStashProductController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
    private readonly GreenStashNavigation $navigation,
    private readonly GreenStashProductReader $products,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  public function detail(string $produto): array {
    if (!$this->experiences->access('green_stash', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    if (!ctype_digit($produto) || $produto === '0') {
      throw new NotFoundHttpException();
    }
    $entity = $this->entities->getStorage('commerce_product')->load($produto);
    if ($entity === NULL || $entity->bundle() !== 'produto_loja') {
      throw new NotFoundHttpException();
    }
    if (!$this->products->canManage($entity, $this->account)) {
      throw new AccessDeniedHttpException();
    }

    $build = $this->navigation->build('produtos');
    $build['heading']['#value'] = $entity->label();
    $build['notice']['#value'] = 'Detalhes do produto. A edição dentro do Green Stash será disponibilizada na próxima fase.';
    $build['back'] = [
      '#type' => 'link',
      '#title' => 'Voltar aos produtos',
      '#url' => Url::fromRoute('aculta_commerce.green_stash_area', ['secao' => 'produtos']),
    ];
    $build['detail'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'green-stash-product-detail'],
      'id' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Produto nº ' . $entity->id(),
      ],
      'publication' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $entity->isPublished() ? 'Publicado' : 'Rascunho',
      ],
    ];
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

}
