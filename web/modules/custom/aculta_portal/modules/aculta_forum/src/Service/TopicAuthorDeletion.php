<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_cgu\Service\ReportService;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Author deletion of a topic: the topic stays open with an automatic message and moderation is notified.
 */
final class TopicAuthorDeletion {

  /**
   * Key-value collection of topic tombstones, keyed by node id. Value 'autor' = deleted by its author.
   */
  public const TOMBSTONES = 'aculta_forum_topic_tombstones';

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly ReportService $reports,
  ) {}

  /**
   * Marks the topic as deleted by its author and notifies moderation through the CGU.
   */
  public function mark(NodeInterface $topic, AccountInterface $author): void {
    $this->keyValue->get(self::TOMBSTONES)->set((string) $topic->id(), 'autor');
    $this->reports->createReport($topic, $author, 'Tópico excluído pelo(a) autor(a): aviso à moderação', 'forum');
  }

  /**
   * Whether the topic was deleted by its author.
   */
  public function isDeletedByAuthor(int $nodeId): bool {
    return $this->keyValue->get(self::TOMBSTONES)->get((string) $nodeId) === 'autor';
  }

}
