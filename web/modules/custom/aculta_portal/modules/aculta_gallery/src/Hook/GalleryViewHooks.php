<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Página do álbum: as fotos do álbum atual aparecem num carrossel VVJB (uma por slide).
 *
 * A view aculta_album_fotos traz as imagens do álbum com paginação real. O tema envolve o VVJB no componente
 * de carrossel na apresentação do tema. Aqui só entra o contexto do álbum (argumento da view).
 */
final class GalleryViewHooks {

  /** View de fotos do álbum (config/sync/views.view.aculta_album_fotos.yml). */
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
    $view = $this->entityTypeManager->getStorage('view')->load(self::VIEW_ID);
    if ($view === NULL) {
      return;
    }
    $executable = $this->viewExecutableFactory->get($view);
    $slideshow = $executable->buildRenderable('block_1', [$entity->id()]);
    $build['album_stage'] = [
      '#type' => 'container',
      '#weight' => -10,
      // Comportamento e ligação com o VVJB ficam em data-*; a aparência é do tema.
      // photoswipe-gallery é classe de comportamento do PhotoSwipe (agrupa as fotos num só lightbox), não de design.
      '#attributes' => ['data-aculta-album-stage' => 'true', 'class' => ['photoswipe-gallery']],
      'slideshow' => $slideshow,
      'thumbs' => $this->thumbs($entity),
    ];
  }

  /**
   * Miniaturas do álbum. Cada botão leva o número do slide (1 a N) no VVJB.
   *
   * @return array<string, mixed>
   *   Render array com um botão por foto do álbum, na mesma ordem do carrossel.
   */
  private function thumbs(EntityInterface $entity): array {
    $thumbs = [
      '#type' => 'container',
      '#attributes' => ['data-aculta-album-thumbs' => 'true'],
      '#cache' => ['tags' => $entity->getCacheTags()],
    ];
    $position = 0;
    foreach ($entity->get('images')->referencedEntities() as $media) {
      if (!$media->access('view')) {
        continue;
      }
      $file = $media->get('field_media_image')->entity;
      if ($file === NULL) {
        continue;
      }
      $position++;
      $thumbs[$position] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#attributes' => [
          'type' => 'button',
          'data-aculta-album-thumb' => (string) $position,
          'aria-label' => 'Foto ' . $position,
        ],
        'image' => [
          '#theme' => 'image_style',
          '#style_name' => 'media_gallery_thumb',
          '#uri' => $file->getFileUri(),
          '#alt' => (string) ($media->get('field_media_image')->alt ?: $media->label()),
          '#attributes' => ['loading' => 'lazy'],
        ],
      ];
    }
    return $thumbs;
  }

}
