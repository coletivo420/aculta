<?php

declare(strict_types=1);

namespace Drupal\aculta_index_forum_access_test\Hook;

use Drupal\aculta_forum\Hook\ForumPostAccessHooks;
use Drupal\aculta_forum\Hook\ForumAttachmentAccessHooks;
use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Exposes production Forum access methods to the Kernel harness.
 */
final class ForumAccessTestHooks {

  public function __construct(
    private readonly RestrictedForumAccessHooks $restrictedForumAccess,
    private readonly ForumPostAccessHooks $forumPostAccess,
    private readonly ForumAttachmentAccessHooks $forumAttachmentAccess,
  ) {}

  /**
   * Delegates entity access to the production restricted Forum policy.
   */
  #[Hook('entity_access')]
  public function restrictedForumEntityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    return $this->restrictedForumAccess->entityAccess($entity, $operation, $account);
  }

  /**
   * Delegates ForumPost entity access to the production policy.
   */
  #[Hook('entity_access')]
  public function forumPostEntityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    return $this->forumPostAccess->entityAccess($entity, $operation, $account);
  }

  /**
   * Delegates attachment access to the production Forum Media policy.
   */
  #[Hook('media_access')]
  public function forumAttachmentMediaAccess(MediaInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    return $this->forumAttachmentAccess->mediaAccess($entity, $operation, $account);
  }

  /**
   * Delegates node grants to the production Forum hook.
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    return $this->restrictedForumAccess->nodeGrants($account, $operation);
  }

  /**
   * Delegates node access records to the production Forum hook.
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    return $this->restrictedForumAccess->nodeAccessRecords($node);
  }

}
