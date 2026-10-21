<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Adds revisionable posts to the canonical public topic presentation.
 */
final class ForumPostThreadHooks {

  public function __construct(
    private readonly ForumPostThreadBuilder $threadBuilder,
  ) {}

  /**
   * Renders revisionable posts before the still-active Comment field.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic' || $view_mode !== 'full') {
      return;
    }

    $posts = $this->threadBuilder->build($entity);
    if ($posts !== []) {
      $build['aculta_forum_revisionable_posts'] = $posts;
    }
  }

}
