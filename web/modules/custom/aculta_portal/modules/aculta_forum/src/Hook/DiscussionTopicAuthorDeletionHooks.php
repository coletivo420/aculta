<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;

/**
 * Shows the automatic message on a topic deleted by its author; the topic stays open.
 */
final class DiscussionTopicAuthorDeletionHooks {

  public function __construct(
    private readonly TopicAuthorDeletion $deletion,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * First comment of this topic, by another account, posted after the user's last read.
   *
   * The history table is written by the core read endpoint after the page renders,
   * so during render the previous timestamp is still the one used here.
   */
  private function firstUnreadCommentId(NodeInterface $topic): int {
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      return 0;
    }
    $lastReadRaw = $this->database->select('history', 'h')
      ->fields('h', ['timestamp'])
      ->condition('h.nid', (int) $topic->id())
      ->condition('h.uid', $uid)
      ->execute()?->fetchField();
    $lastRead = is_numeric($lastReadRaw) ? (int) $lastReadRaw : 0;
    $ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(TRUE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('status', 1)
      ->condition('created', $lastRead, '>')
      ->condition('uid', $uid, '<>')
      ->sort('cid', 'ASC')
      ->range(0, 1)
      ->execute();
    return $ids === [] ? 0 : (int) reset($ids);
  }

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
    $firstUnread = $this->firstUnreadCommentId($entity);
    if ($firstUnread > 0) {
      $build['jump_to_first_unread'] = [
        '#type' => 'link',
        '#title' => t('Primeira não lida'),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $entity->id()], ['fragment' => 'aculta-comment-' . $firstUnread]),
        '#attributes' => ['class' => ['aculta-forum-jump-unread']],
        '#cache' => ['contexts' => ['user'], 'max-age' => 0],
      ];
    }
    if ($entity->isSticky()) {
      $build['pinned_badge'] = [
        '#markup' => Markup::create('<p class="forum-topic-pinned">' . t('Fixado') . '</p>'),
        '#weight' => -60,
      ];
    }
    if ($this->deletion->isArchived((int) $entity->id())) {
      $build['archived_notice'] = [
        '#markup' => Markup::create('<p class="forum-topic-archived">' . t('Tópico arquivado: somente leitura.') . '</p>'),
        '#weight' => -55,
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
