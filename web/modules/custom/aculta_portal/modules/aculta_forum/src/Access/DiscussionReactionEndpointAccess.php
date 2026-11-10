<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_forum\Service\DiscussionReactionAccess;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access check for the discussion reaction endpoint.
 */
class DiscussionReactionEndpointAccess {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DiscussionReactionAccess $reactionAccess,
  ) {}

  /**
   * Checks access to react to a given entity.
   */
  public function access(string $entity_type, string $entity_id, AccountInterface $account): AccessResultInterface {
    if (!in_array($entity_type, ['comment', 'aculta_forum_post', 'node'], TRUE)) {
      return AccessResult::forbidden('Invalid entity type for reactions.');
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    if ($entity === NULL) {
      return AccessResult::forbidden('Target entity not found.');
    }

    return $this->reactionAccess->checkAccess($entity, $account);
  }

}
