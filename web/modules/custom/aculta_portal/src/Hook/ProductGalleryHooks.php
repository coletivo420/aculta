<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Galeria do produto da loja: prepara a view VVJS (galeria_produto) com as mídias do produto.
 *
 * O Portal entrega ao tema uma renderização pronta em aculta_product_gallery; o tema só imprime.
 */
final class ProductGalleryHooks {

  public const VIEW_ID = 'galeria_produto';

  public const BUNDLE = 'produto_loja';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'views.executable')]
    private readonly ViewExecutableFactory $viewExecutableFactory,
  ) {}

  #[Hook('preprocess_commerce_product')]
  public function preprocessCommerceProduct(array &$variables): void {
    $product = $variables['elements']['#commerce_product'] ?? NULL;
    if ($product === NULL || $product->bundle() !== self::BUNDLE || !$product->hasField('field_galeria')) {
      return;
    }
    $ids = [];
    foreach ($product->get('field_galeria') as $item) {
      if ($item->target_id) {
        $ids[] = (string) $item->target_id;
      }
    }
    $view = $this->entityTypeManager->getStorage('view')->load(self::VIEW_ID);
    if ($ids === [] || $view === NULL) {
      return;
    }
    $executable = $this->viewExecutableFactory->get($view);
    $variables['aculta_product_gallery'] = $executable->buildRenderable('default', [implode('+', $ids)]);
  }

}
