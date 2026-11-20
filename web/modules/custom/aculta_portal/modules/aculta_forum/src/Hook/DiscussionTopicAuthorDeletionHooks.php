<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;

/**
 * Shows the automatic message on a topic deleted by its author; the topic stays open.
 */
final class DiscussionTopicAuthorDeletionHooks {

  public function __construct(private readonly TopicAuthorDeletion $deletion) {}

  /**
   * Replaces the body with the automatic message when the author deleted the topic.
   *
   * @param array<string, mixed> $build
   *   Node render array.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $entity): void {
    if ($entity->bundle() !== 'aculta_forum_topic' || !$this->deletion->isDeletedByAuthor((int) $entity->id())) {
      return;
    }
    $build['body'] = [
      '#markup' => Markup::create('<p class="forum-topic-deleted-by-author">' . t('Conteúdo excluído pelo(a) autor(a).') . '</p>'),
      '#cache' => ['tags' => $entity->getCacheTags()],
    ];
  }

}
