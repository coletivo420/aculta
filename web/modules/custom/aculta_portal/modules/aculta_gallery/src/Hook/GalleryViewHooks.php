<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Página do álbum.
 *
 * Entrega imagens em grade SDC para o álbum editorial e o Fórum.
 *
 * PhotoSwipe e o controle de acesso/cache das imagens continuam no módulo.
 */
final class GalleryViewHooks {

  /**
   * View de fotos do álbum (config/sync/views.view.aculta_album_fotos.yml).
   */
  private const VIEW_ID = 'aculta_album_fotos';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'views.executable')]
    private readonly ViewExecutableFactory $viewExecutableFactory,
  ) {}

  /**
   * Implements hook_media_gallery_view().
   */
  #[Hook('media_gallery_view')]
  public function mediaGalleryView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!in_array($view_mode, ['default', 'full'], TRUE)) {
      return;
    }
    $is_forum_gallery = $entity->hasField('field_gallery_purpose')
      && $entity->get('field_gallery_purpose')->value === 'forum';
    $view_id = $is_forum_gallery ? 'aculta_forum_gallery_slides' : self::VIEW_ID;
    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    if ($view === NULL) {
      return;
    }
    $executable = $this->viewExecutableFactory->get($view);
    $gallery = $executable->buildRenderable('block_1', [$entity->id()]);
    $build['album_stage'] = [
      '#type' => 'container',
      '#weight' => -10,
      '#attributes' => ['class' => ['photoswipe-gallery']],
      'gallery' => $gallery,
    ];
    // Bubble the access decision and every image dependency to the gallery.
    $cacheability = CacheableMetadata::createFromObject($entity);
    foreach ($entity->get('images')->referencedEntities() as $media) {
      $access = $media->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($media);
      $cacheability->addCacheableDependency($access);
      $field_name = $this->imageFieldName($media);
      $file = $field_name !== NULL ? $media->get($field_name)->entity : NULL;
      if ($file !== NULL) {
        $cacheability->addCacheableDependency($file);
      }
    }
    $cacheability->applyTo($build['album_stage']);
  }

  /**
   * Returns the image field for the supported Gallery Media bundle.
   */
  private function imageFieldName(MediaInterface $media): ?string {
    $field = $media->bundle() === 'forum_attachment'
      ? 'field_media_forum_image'
      : 'field_media_image';
    return $media->hasField($field) && !$media->get($field)->isEmpty() ? $field : NULL;
  }

}
