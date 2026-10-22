<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Projects allowlisted metadata for the current Wiki entry translation.
 *
 * Wiki relations to other entries, taxonomy terms and media remain deferred
 * until the Wiki module's content and access contracts are finalized.
 */
final class WikiContentIndexProvider implements ContentIndexProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'wiki';
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['node' => ['wiki_entry']];
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry' || !$entity->isDefaultRevision()) {
      return NULL;
    }

    $domainId = NULL;
    if ($entity->hasField('field_domain_source') && !$entity->get('field_domain_source')->isEmpty()) {
      $domainId = (string) $entity->get('field_domain_source')->target_id;
    }

    return new ContentIndexProjection(
      'node',
      (string) $entity->id(),
      $entity->language()->getId(),
      $entity->bundle(),
      (string) $entity->uuid(),
      (string) $entity->label(),
      $domainId,
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
    );
  }

}
