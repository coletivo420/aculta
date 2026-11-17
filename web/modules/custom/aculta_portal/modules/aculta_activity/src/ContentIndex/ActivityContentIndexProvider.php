<?php

declare(strict_types=1);

namespace Drupal\aculta_activity\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Projects the minimal metadata of Portal activity nodes into the Index.
 */
final class ActivityContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'activity';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 3;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['node' => ['activity']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessSiteKey(ContentEntityInterface $entity): ?string {
    return $entity instanceof NodeInterface && $entity->bundle() === 'activity' ? 'main' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof NodeInterface && $entity->bundle() === 'activity' && $entity->isDefaultRevision();
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity) || !$entity instanceof NodeInterface) {
      return NULL;
    }

    $domainId = NULL;
    if ($entity->hasField('field_domain_source') && !$entity->get('field_domain_source')->isEmpty()) {
      $domainId = (string) $entity->get('field_domain_source')->target_id;
    }

    $relations = [];
    // Activity's editorial image is an explicit Media reference. Keep only the
    // typed identity edge; Media access is revalidated by Index consumers.
    if ($entity->hasField('field_media_image') && !$entity->get('field_media_image')->isEmpty()) {
      /** @var array<int, array<string, mixed>> $imageItems */
      $imageItems = $entity->get('field_media_image')->getValue();
      foreach ($imageItems as $item) {
        $targetId = $item['target_id'] ?? NULL;
        if ($targetId !== NULL && $targetId !== '') {
          $targetIdStr = is_scalar($targetId) ? (string) $targetId : '';
          if ($targetIdStr !== '') {
            $key = 'editorial_image:media:' . $targetIdStr;
            $relations[$key] = new ContentIndexRelation('editorial_image', 'media', $targetIdStr);
          }
        }
      }
    }

    return new ContentIndexProjection(
      'node',
      (string) $entity->id(),
      $entity->language()->getId(),
      'activity',
      (string) $entity->uuid(),
      (string) $entity->label(),
      $domainId,
      (bool) $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
      relations: array_values($relations),
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

}
