<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\comment\CommentInterface;
use Drupal\Core\Url;

/**
 * Interface for discussion comment permalink builders.
 */
interface DiscussionCommentPermalinkBuilderInterface {

  /**
   * Builds the permalink URL to the commented host entity.
   */
  public function build(CommentInterface $comment): ?Url;

  /**
   * Builds the host topic URL with the comment's page and anchor.
   */
  public function hostUrl(CommentInterface $comment): ?Url;

}
