<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Keeps the album slideshow rows identical to the access-filtered thumbnails. */
final class AlbumSlidesAccessHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_views_query_alter() for the album slideshow.
   */
  #[Hook('views_query_alter')]
  public function filterAlbumSlides(ViewExecutable $view, QueryPluginBase $query): void {
    if ($view->id() !== 'aculta_album_fotos' || !$query instanceof Sql) {
      return;
    }
    $galleryId = (int) ($view->args[0] ?? 0);
    $gallery = $galleryId > 0 ? $this->entityTypeManager->getStorage('media_gallery')->load($galleryId) : NULL;
    $allowedIds = [];
    if ($gallery !== NULL) {
      foreach ($gallery->get('images')->referencedEntities() as $media) {
        $imageFile = $media->get('field_media_image')->entity;
        if ($imageFile !== NULL && $media->access('view', NULL, TRUE)->isAllowed()) {
          $allowedIds[] = (int) $media->id();
        }
      }
    }

    $imagesTable = $query->getTableInfo('media_gallery__images');
    if ($imagesTable === NULL) {
      // A changed View must fail closed rather than expose unfiltered slides.
      $query->addWhereExpression(0, '1 = 0');
      return;
    }
    // Filter the gallery-to-Media reference. The separate field_media_image
    // field points to File IDs, which are not the Media entity IDs in this set.
    $mediaTarget = $imagesTable['alias'] . '.images_target_id';
    if ($allowedIds === []) {
      $query->addWhereExpression(0, '1 = 0');
    }
    else {
      $query->addWhere(0, $mediaTarget, $allowedIds, 'IN');
    }

    // Match the Media Gallery entity-reference delta, which is also the order
    // used by referencedEntities() when the thumbnail controls are built.
    $query->addOrderBy($imagesTable['alias'], 'delta', 'ASC');
  }

}
