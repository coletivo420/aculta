<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Keeps product links in the shared cart on the SHOP domain.
 */
final class CartProductLinkHooks {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Rewrites the cart variation's product link to the canonical SHOP host.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof ProductVariationInterface || $display->getMode() !== 'cart'
      || !isset($build['product_id']) || !is_array($build['product_id'])) {
      return;
    }

    $product = $entity->getProduct();
    if ($product === NULL) {
      return;
    }
    $url = $this->domainPurposeManager->routeUrl(
      'shop',
      'entity.commerce_product.canonical',
      ['commerce_product' => $product->id()],
    );
    foreach ($build['product_id'] as &$item) {
      if (!is_array($item) || ($item['#type'] ?? NULL) !== 'link') {
        continue;
      }
      if ($url === NULL) {
        // Never leave a product link targeting the current (MAIN) host when
        // the SHOP domain is not configured.
        $item['#plain_text'] = $item['#title'] ?? $product->label();
        unset($item['#type'], $item['#url'], $item['#options']);
        continue;
      }
      $item['#url'] = $url;
      $item['#options'] = $url->getOptions();
    }
    unset($item);

    $build['#cache']['contexts'] = array_values(array_unique(array_merge(
      $build['#cache']['contexts'] ?? [],
      ['domain'],
    )));
  }

}
