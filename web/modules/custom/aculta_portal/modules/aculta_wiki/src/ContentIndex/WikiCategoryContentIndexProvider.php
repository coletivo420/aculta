<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Provides minimal Index records for Wiki category vocabulary terms.
 */
final class WikiCategoryContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'wiki_category';
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
    return ['taxonomy_term' => ['wiki_category']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof TermInterface && $entity->bundle() === 'wiki_category'
      ? 'wiki'
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof TermInterface
      && $entity->bundle() === 'wiki_category'
      && $entity->isDefaultRevision();
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    return new ContentIndexProjection(
      'taxonomy_term',
      (string) $entity->id(),
      $entity->language()->getId(),
      $entity->bundle(),
      (string) $entity->uuid(),
      (string) $entity->getName(),
      NULL,
      $entity->isPublished(),
      max(0, (int) $entity->getRevisionCreationTime()),
      max(0, (int) $entity->getChangedTime()),
    );
  }

}
