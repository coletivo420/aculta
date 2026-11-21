<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_cgu\Service\ReportService;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Author deletion of a topic: the topic stays open with an automatic message and moderation is notified.
 */
class TopicAuthorDeletion {

  /**
   * Key-value collection of topic tombstones, keyed by node id. Value 'autor' = deleted by its author.
   */
  public const TOMBSTONES = 'aculta_forum_topic_tombstones';

  /**
   * Key-value collection of closed topics, keyed by node id (TRUE = closed by moderation).
   */
  public const CLOSED = 'aculta_forum_topic_closed';

  /**
   * Key-value collection of archived topics, keyed by node id (TRUE = archived by moderation).
   */
  public const ARCHIVED = 'aculta_forum_topic_archived';

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly ReportService $reports,
    private readonly CacheTagsInvalidatorInterface $cacheInvalidator,
  ) {}

  /**
   * Invalidates the cached render of a topic so pages show the new state at once.
   */
  private function invalidate(NodeInterface $topic): void {
    $this->cacheInvalidator->invalidateTags($topic->getCacheTags());
  }

  /**
   * Marks the topic as deleted by its author and notifies moderation through the CGU.
   */
  public function mark(NodeInterface $topic, AccountInterface $author): void {
    $this->keyValue->get(self::TOMBSTONES)->set((string) $topic->id(), 'autor');
    $this->invalidate($topic);
    $this->reports->createReport($topic, $author, 'Tópico excluído pelo(a) autor(a): aviso à moderação', 'forum');
  }

  /**
   * Marks the topic as removed by moderation: the block shows the automatic moderation message.
   */
  public function markRemovedByModeration(NodeInterface $topic): void {
    $this->keyValue->get(self::TOMBSTONES)->set((string) $topic->id(), 'moderacao');
    $this->invalidate($topic);
  }

  /**
   * Returns 'autor', 'moderacao' or NULL for a topic.
   */
  public function tombstone(int $nodeId): ?string {
    $value = $this->keyValue->get(self::TOMBSTONES)->get((string) $nodeId);
    return is_string($value) ? $value : NULL;
  }

  /**
   * Whether the topic is closed by moderation.
   */
  public function isClosed(int $nodeId): bool {
    return (bool) $this->keyValue->get(self::CLOSED)->get((string) $nodeId, FALSE);
  }

  /**
   * Opens or closes a topic (moderation). Closed topics stay visible for reading.
   */
  public function setClosed(NodeInterface $topic, bool $closed): void {
    $this->keyValue->get(self::CLOSED)->set((string) $topic->id(), $closed);
    $this->invalidate($topic);
  }

  /**
   * Whether the topic is archived by moderation (read-only, kept for reference).
   */
  public function isArchived(int $nodeId): bool {
    return (bool) $this->keyValue->get(self::ARCHIVED)->get((string) $nodeId, FALSE);
  }

  /**
   * Archives or unarchives a topic (moderation).
   */
  public function setArchived(NodeInterface $topic, bool $archived): void {
    $this->keyValue->get(self::ARCHIVED)->set((string) $topic->id(), $archived);
    $this->invalidate($topic);
  }

  /**
   * Whether the topic was deleted by its author.
   */
  public function isDeletedByAuthor(int $nodeId): bool {
    return $this->tombstone($nodeId) === 'autor';
  }

}
