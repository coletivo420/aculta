<?php

declare(strict_types=1);

namespace Drupal\aculta_index_representative_test\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Projects a synthetic node bundle with optional explicit relations.
 *
 * The fixture exercises diverse domains, languages, publication states and
 * relations without depending on a production owner module.
 */
final class RepresentativeContentProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface {

  public function __construct(
    private readonly string $providerId,
    private readonly string $bundle,
    private readonly ?string $relationType = NULL,
    private readonly ?string $targetBundle = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return $this->providerId;
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
    return ['node' => [$this->bundle]];
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof NodeInterface
      && $entity->bundle() === $this->bundle
      && $entity->isDefaultRevision();
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    $domainId = NULL;
    if ($entity->hasField('field_representative_domain') && !$entity->get('field_representative_domain')->isEmpty()) {
      $domainId = (string) $entity->get('field_representative_domain')->value;
    }

    $relations = [];
    if ($this->relationType !== NULL && $this->targetBundle !== NULL
      && $entity->hasField('field_representative_related')
      && !$entity->get('field_representative_related')->isEmpty()) {
      foreach ($entity->get('field_representative_related')->referencedEntities() as $target) {
        if ($target instanceof NodeInterface && $target->bundle() === $this->targetBundle) {
          $relations[] = new ContentIndexRelation($this->relationType, 'node', (string) $target->id());
        }
      }
    }

    return new ContentIndexProjection(
      'node',
      (string) $entity->id(),
      $entity->language()->getId(),
      $this->bundle,
      (string) $entity->uuid(),
      (string) $entity->label(),
      $domainId,
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
      relations: $relations,
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

}
