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
   * @param \Drupal\node\NodeInterface $entity
   *   The node being rendered.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $entity): void {
    if ($entity->bundle() !== 'aculta_forum_topic') {
      return;
    }
    if ($entity->isSticky()) {
      $build['pinned_badge'] = [
        '#markup' => Markup::create('<p class="forum-topic-pinned">' . t('Fixado') . '</p>'),
        '#weight' => -60,
      ];
    }
    if ($this->deletion->isClosed((int) $entity->id())) {
      $build['closed_notice'] = [
        '#markup' => Markup::create('<p class="forum-topic-closed">' . t('Tópico fechado pela moderação.') . '</p>'),
        '#weight' => -50,
      ];
    }
    $tombstone = $this->deletion->tombstone((int) $entity->id());
    if ($tombstone === NULL) {
      return;
    }
    $message = $tombstone === 'moderacao' ? t('Conteúdo removido pela moderação.') : t('Conteúdo excluído pelo(a) autor(a).');
    $build['body'] = [
      '#markup' => Markup::create('<p class="forum-topic-tombstone forum-topic-tombstone--' . $tombstone . '">' . $message . '</p>'),
      '#cache' => ['tags' => $entity->getCacheTags()],
    ];
  }

}
