<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;

/**
 * Builds canonical permalink URLs pointing directly to the host entity.
 *
 * Ensures human-facing permalinks never target the retired standalone route
 * (/comment/{cid}, blocked by D-65) while preserving the host's route,
 * language, pagination query, and DOM anchor.
 */
final class DiscussionCommentPermalinkBuilder implements DiscussionCommentPermalinkBuilderInterface {

  public function __construct(
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * Builds the permalink URL to the commented host entity.
   */
  public function build(CommentInterface $comment): ?Url {
    $host = $comment->getCommentedEntity();
    if (!$host instanceof EntityInterface) {
      return NULL;
    }

    $url = $host->toUrl('canonical');
    $options = $url->getOptions();
    $options['fragment'] = 'comment-' . $comment->id();

    // Preserve existing query params, and if pager element 0 is present, ensure it's maintained.
    $page = $this->pagerManager->findPage(0);
    if ($page > 0) {
      $currentQuery = $options['query'] ?? [];
      $options['query'] = $this->pagerManager->getUpdatedParameters($currentQuery, 0, $page);
    }

    $url->setOptions($options);
    return $url;
  }

}
