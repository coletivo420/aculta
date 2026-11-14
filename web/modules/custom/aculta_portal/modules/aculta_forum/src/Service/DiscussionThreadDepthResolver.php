<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Resolves visual depth and normalizes effective parent IDs for discussion comments.
 */
class DiscussionThreadDepthResolver {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns configured maximum reply depth (0 = off, 2 = 2 levels, 3 = 3 levels).
   */
  public function getMaxDepth(): int {
    $value = $this->configFactory->get('aculta_forum.settings')->get('reply_max_depth');
    if ($value === NULL) {
      return 2;
    }
    $intVal = (int) $value;
    return in_array($intVal, [0, 2, 3], TRUE) ? $intVal : 2;
  }

  /**
   * Resolves effective visual depth for a comment, bounded by reply_max_depth.
   */
  public function resolveDepth(CommentInterface $comment): int {
    $maxDepth = $this->getMaxDepth();
    if ($maxDepth === 0) {
      return 0;
    }
    $rawDepth = $this->resolveRawDepth($comment);
    return min($maxDepth, $rawDepth);
  }

  /**
   * Resolves raw unconstrained thread depth of a comment.
   */
  public function resolveRawDepth(CommentInterface $comment): int {
    $thread = (string) $comment->getThread();
    if ($thread !== '') {
      $parts = explode('.', rtrim($thread, '/'));
      return max(0, count($parts) - 1);
    }
    if ($comment->hasParentComment()) {
      $parent = $comment->getParentComment();
      return $parent ? $this->resolveRawDepth($parent) + 1 : 0;
    }
    return 0;
  }

  /**
   * Resolves effective parent ID for replies based on max depth constraints.
   */
  public function resolveEffectiveParentId(?CommentInterface $parent): ?int {
    if ($parent === NULL) {
      return NULL;
    }

    $maxDepth = $this->getMaxDepth();
    if ($maxDepth === 0) {
      return NULL;
    }

    $parentDepth = $this->resolveRawDepth($parent);
    if ($parentDepth < $maxDepth) {
      return (int) $parent->id();
    }

    // When the parent is already at or beyond max depth, normalize to the
    // ancestor that sits at (maxDepth - 1), so the new reply sits at maxDepth.
    $targetDepth = $maxDepth - 1;
    $current = $parent;
    while ($current && $this->resolveRawDepth($current) > $targetDepth) {
      $current = $current->hasParentComment() ? $current->getParentComment() : NULL;
    }

    return $current ? (int) $current->id() : (int) $parent->id();
  }

}
