<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Página do álbum: as fotos aparecem numa sequência VVJS, uma por slide.
 *
 * A view aculta_album_fotos traz as imagens do álbum com paginação real. O tema
 * apresenta o VVJS e a navegação por miniaturas. Aqui só entra o contexto do
 * álbum (argumento da view).
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
    #[Autowire(service: 'aculta_image.render')]
    private readonly ImageRenderer $imageRenderer,
  ) {}

  /**
   * Implements hook_media_gallery_view().
   */
  #[Hook('media_gallery_view')]
  public function mediaGalleryView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!in_array($view_mode, ['default', 'full'], TRUE)) {
      return;
    }
    $view_id = $entity->hasField('field_gallery_purpose')
      && $entity->get('field_gallery_purpose')->value === 'forum'
      ? 'aculta_forum_gallery_slides'
      : self::VIEW_ID;
    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    if ($view === NULL) {
      return;
    }
    $executable = $this->viewExecutableFactory->get($view);
    $slideshow = $executable->buildRenderable('block_1', [$entity->id()]);
    $build['album_stage'] = [
      '#type' => 'container',
      '#weight' => -10,
      // Comportamento e ligação com VVJS usam data-*; aparência é do tema.
      // photoswipe-gallery agrupa as fotos para o PhotoSwipe.
      '#attributes' => ['data-aculta-album-stage' => 'true', 'class' => ['photoswipe-gallery']],
      'slideshow' => $slideshow,
      'thumbs' => $this->thumbs($entity),
    ];
    // Access decisions affect both the VVJS result and the thumbnail index.
    // Bubble every dependency at the shared parent, including denied results.
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
   * Miniaturas do álbum. Cada botão leva o número do slide (1 a N) no VVJS.
   *
   * @return array<string, mixed>
   *   Render array com um botão por foto do álbum, na mesma ordem da sequência.
   */
  private function thumbs(EntityInterface $entity): array {
    $thumbs = [
      '#type' => 'container',
      '#attributes' => ['data-aculta-album-thumbs' => 'true'],
      '#cache' => ['tags' => $entity->getCacheTags()],
    ];
    $position = 0;
    foreach ($entity->get('images')->referencedEntities() as $media) {
      $access = $media->access('view', NULL, TRUE);
      if (!$access->isAllowed()) {
        continue;
      }
      $field_name = $this->imageFieldName($media);
      $file = $field_name !== NULL ? $media->get($field_name)->entity : NULL;
      if ($file === NULL) {
        continue;
      }
      $position++;
      $image_field = $media->get($field_name);
      $alt = (string) ($image_field->alt ?? $media->label());
      $thumb_image = $this->imageRenderer->build('galeria_miniatura', $file->getFileUri(), $alt, ['loading' => 'lazy']);
      $thumbs[$position] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#attributes' => [
          'type' => 'button',
          'data-aculta-album-thumb' => (string) $position,
          'aria-label' => 'Foto ' . $position,
        ],
        'image' => $thumb_image,
      ];
    }
    return $thumbs;
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
