<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Hook;

use Drupal\aculta_image\ImageCatalog;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Derivados 1x e 2x da finalidade de card (aculta_image). Movido do tema: a fonte de srcset é o sistema de imagens.
 */
final class ImageStyleHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_image.catalog')]
    private readonly ImageCatalog $catalog,
  ) {}

  /** Implements hook_preprocess_image_style(). Só o card 1x recebe srcset com o 2x; outras imagens não mudam. */
  #[Hook('preprocess_image_style')]
  public function preprocessImageStyle(array &$variables): void {
    $umX = $this->catalog->styleFor('card');
    $doisX = $this->catalog->styleFor('card_2x');
    if (($variables['style_name'] ?? '') !== $umX || empty($variables['uri']) || $umX === NULL || $doisX === NULL) {
      return;
    }
    $styles = $this->entityTypeManager->getStorage('image_style');
    $one_x = $styles->load($umX);
    $two_x = $styles->load($doisX);
    if (!$one_x instanceof ImageStyleInterface || !$two_x instanceof ImageStyleInterface || !isset($variables['image']['#attributes'])) {
      return;
    }
    $variables['image']['#attributes']['srcset'] = $one_x->buildUrl($variables['uri']) . ' 1x, '
      . $two_x->buildUrl($variables['uri']) . ' 2x';
  }

}
