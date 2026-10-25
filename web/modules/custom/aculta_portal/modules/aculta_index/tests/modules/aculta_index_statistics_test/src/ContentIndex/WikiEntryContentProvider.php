<?php

declare(strict_types=1);

namespace Drupal\aculta_index_statistics_test\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessPolicyProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Projects Wiki-shaped nodes for Index-to-Statistics Kernel coverage.
 */
final class WikiEntryContentProvider implements ContentIndexProviderInterface, ContentIndexAccessPolicyProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'test_wiki_entry';
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
    return ['node' => ['wiki_entry']];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(ContentEntityInterface $entity, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($entity instanceof NodeInterface && $entity->isPublished())
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry') {
      return NULL;
    }

    return new ContentIndexProjection(
      'node',
      (string) $entity->id(),
      $entity->language()->getId(),
      'wiki_entry',
      (string) $entity->uuid(),
      (string) $entity->label(),
      NULL,
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
    );
  }

}
