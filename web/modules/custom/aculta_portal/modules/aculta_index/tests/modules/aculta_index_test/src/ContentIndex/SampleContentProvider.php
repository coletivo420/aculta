<?php

declare(strict_types=1);

namespace Drupal\aculta_index_test\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Projects generic content nodes for rebuild integration coverage.
 */
final class SampleContentProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface {

  /**
   * {@inheritdoc} */
  public function getProviderId(): string {
    return 'test_sample_content';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 1;
  }

  /**
   * {@inheritdoc} */
  public function getSupportedEntityTypes(): array {
    return ['node' => ['sample_content']];
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof NodeInterface
      && $entity->bundle() === 'sample_content'
      && $entity->label() !== 'Excluded from sample index';
  }

  /**
   * {@inheritdoc} */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }
    return new ContentIndexProjection(
      'node',
      (string) $entity->id(),
      $entity->language()->getId(),
      'sample_content',
      (string) $entity->uuid(),
      (string) $entity->label(),
      NULL,
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
    );
  }

}
