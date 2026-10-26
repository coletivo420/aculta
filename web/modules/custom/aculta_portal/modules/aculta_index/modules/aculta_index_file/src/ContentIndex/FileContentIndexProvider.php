<?php

declare(strict_types=1);

namespace Drupal\aculta_index_file\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;

/**
 * Minimal Index projection for permanent File entities.
 *
 * This provider exists only so the Statistics metric
 * `media.files.requests` (dimension `file:file`) can be correlated through the
 * optional bridge. It deliberately does not project the filename, URI, MIME
 * type, size or any file contents. Entity Access for the file remains the
 * authority, including the referencing entity for private files.
 *
 * Temporary files are not indexed.
 */
final class FileContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'file';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['file' => ['file']];
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof FileInterface && $entity->isPermanent();
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    return new ContentIndexProjection(
      'file',
      (string) $entity->id(),
      $entity->language()->getId(),
      'file',
      (string) $entity->uuid(),
      '',
      NULL,
      TRUE,
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

}
