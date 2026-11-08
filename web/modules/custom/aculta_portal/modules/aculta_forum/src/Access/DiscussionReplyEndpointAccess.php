<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access check for the discussion composer endpoint.
 */
class DiscussionReplyEndpointAccess {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DiscussionReplyAccess $replyAccess,
  ) {}

  /**
   * Checks access to the discussion composer endpoint.
   */
  public function access(string $entity_type, string $entity_id, Request $request, AccountInterface $account): AccessResultInterface {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    if ($storage === NULL) {
      return AccessResult::forbidden('Invalid entity type.');
    }
    $host = $storage->load($entity_id);
    if ($host === NULL) {
      return AccessResult::forbidden('Host entity not found.');
    }

    $parentId = $request->query->get('resposta');
    $parentId = ($parentId !== NULL && $parentId !== '' && ctype_digit((string) $parentId)) ? (int) $parentId : NULL;

    return $this->replyAccess->checkAccess($host, $account, $parentId);
  }

}
