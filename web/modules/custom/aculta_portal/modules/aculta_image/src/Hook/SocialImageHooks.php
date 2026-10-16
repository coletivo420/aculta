<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Hook;

use Drupal\aculta_image\ImageCatalog;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * og:image de páginas de conteúdo: o derivado de compartilhamento (1200×630), não o original (aculta_image, decisão de
 * 2026-10-17). Campos cobertos: imagem direta (`field_image`) e mídia de imagem (`field_media_image`).
 */
final class SocialImageHooks {

  /** Campos que podem guardar a imagem da publicação, na ordem de preferência. */
  private const CAMPOS = ['field_media_image', 'field_image'];

  public function __construct(
    #[Autowire(service: 'aculta_image.catalog')]
    private readonly ImageCatalog $catalog,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Implements hook_metatags_alter(). Troca só o og_image, quando a publicação tem imagem. */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$metatags, array &$context): void {
    $entidade = $context['entity'] ?? NULL;
    if (!$entidade instanceof EntityInterface || $entidade->getEntityTypeId() !== 'node') {
      return;
    }
    $arquivo = $this->arquivoDaPublicacao($entidade);
    if ($arquivo === NULL) {
      return;
    }
    $estilo = $this->estilo();
    if ($estilo === NULL) {
      return;
    }
    $metatags['og_image'] = $estilo->buildUrl($arquivo->getFileUri());
  }

  private function arquivoDaPublicacao(EntityInterface $entidade): ?FileInterface {
    foreach (self::CAMPOS as $campo) {
      if (!$entidade->hasField($campo) || $entidade->get($campo)->isEmpty()) {
        continue;
      }
      $alvo = $entidade->get($campo)->entity;
      if ($alvo instanceof FileInterface) {
        return $alvo;
      }
      if ($alvo !== NULL && $alvo->hasField('field_media_image') && !$alvo->get('field_media_image')->isEmpty()) {
        $arquivo = $alvo->get('field_media_image')->entity;
        if ($arquivo instanceof FileInterface) {
          return $arquivo;
        }
      }
    }
    return NULL;
  }

  private function estilo(): ?ImageStyleInterface {
    $nome = $this->catalog->styleFor('compartilhamento');
    $estilo = $nome === NULL ? NULL : $this->entityTypeManager->getStorage('image_style')->load($nome);
    return $estilo instanceof ImageStyleInterface ? $estilo : NULL;
  }

}
