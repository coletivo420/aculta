<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Página inicial da loja (SHOP). A raiz de loja.aculta serve esta rota, como a raiz de cada subdomínio serve a sua
 * (`/apoio`, `/wiki`, `/cursos`). Mostra o texto da página "Loja da Associação" (nó 372) e os produtos publicados.
 */
final class ShopController extends ControllerBase {

  /** Nó da página inicial da loja ("Loja da Associação"). */
  public const PAGINA_INICIAL_NODE = 372;

  /** Tipo de produto vendido na loja. */
  private const TIPO_PRODUTO = 'produto_loja';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  public function home(): array {
    $cache = (new CacheableMetadata())->addCacheTags(['commerce_product_list']);
    $build = [];

    $pagina = $this->entities->getStorage('node')->load(self::PAGINA_INICIAL_NODE);
    if ($pagina !== NULL) {
      $build['texto'] = $this->entities->getViewBuilder('node')->view($pagina, 'full');
    }

    $cartoes = [];
    foreach ($this->produtosDisponiveis() as $produto) {
      $preco = $this->menorPreco($produto);
      if ($preco === NULL) {
        continue;
      }
      // Elemento de link do Portal: o tema traduz o token data-portal-ui do cartão em classes.
      $cartoes[] = [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'a',
        '#value' => $produto->label() . ' — ' . $preco,
        '#element_attributes' => new Attribute([
          'href' => $produto->toUrl()->toString(),
          'data-portal-ui' => 'shop-product-card',
        ]),
      ];
    }

    $build['produtos'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'shop-products'],
      'titulo' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Produtos')],
      'lista' => $cartoes !== []
        ? $cartoes
        : ['#markup' => '<p>' . $this->t('Nenhum produto disponível no momento.') . '</p>'],
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Produtos publicados da loja, do mais recente para o mais antigo.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface[]
   */
  private function produtosDisponiveis(): array {
    $ids = $this->entities->getStorage('commerce_product')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', self::TIPO_PRODUTO)
      ->sort('created', 'DESC')
      ->execute();
    return $ids === [] ? [] : array_values($this->entities->getStorage('commerce_product')->loadMultiple($ids));
  }

  /** Menor preço entre as variações publicadas, formatado em reais; NULL se o produto não tiver variação vendável. */
  private function menorPreco(ProductInterface $produto): ?string {
    $valores = [];
    foreach ($produto->getVariations() as $variacao) {
      if (!$variacao->isPublished() || $variacao->getPrice() === NULL) {
        continue;
      }
      $valores[] = (float) $variacao->getPrice()->getNumber();
    }
    if ($valores === []) {
      return NULL;
    }
    return 'R$ ' . number_format(min($valores), 2, ',', '.');
  }

}
