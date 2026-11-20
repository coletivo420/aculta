<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Administrative audit trail of topic-level moderation (split and merge).
 *
 * Entries are stored per topic and never shown publicly: only the moderation
 * panel reads them. Paridade Discourse: auditoria de dividir e juntar.
 */
final class TopicModerationAudit {

  /**
   * Key-value collection: node id => list of entries.
   */
  public const COLLECTION = 'aculta_forum_topic_audit';

  /**
   * Creates the service.
   */
  public function __construct(
    private readonly KeyValueFactoryInterface $keyValue,
  ) {}

  /**
   * Records one moderation action on a topic.
   *
   * @param int $nodeId
   *   Topic the action was applied to (source topic).
   * @param string $action
   *   Action machine name: split or merge.
   * @param int $destinationId
   *   Destination topic id.
   * @param int $uid
   *   Moderator user id.
   * @param int $time
   *   Unix timestamp.
   * @param int $count
   *   Number of top-level comments moved.
   */
  public function record(int $nodeId, string $action, int $destinationId, int $uid, int $time, int $count): void {
    $store = $this->keyValue->get(self::COLLECTION);
    $entries = $store->get((string) $nodeId, []);
    $entries = is_array($entries) ? $entries : [];
    $entries[] = [
      'action' => $action,
      'destination' => $destinationId,
      'uid' => $uid,
      'time' => $time,
      'count' => $count,
    ];
    $store->set((string) $nodeId, $entries);
  }

  /**
   * Audit entries of a topic, oldest first.
   *
   * @return list<array{action: string, destination: int, uid: int, time: int, count: int}>
   *   Entries.
   */
  public function entries(int $nodeId): array {
    $entries = $this->keyValue->get(self::COLLECTION)->get((string) $nodeId, []);
    if (!is_array($entries)) {
      return [];
    }
    /** @var list<array{action: string, destination: int, uid: int, time: int, count: int}> $list */
    $list = array_values($entries);
    return $list;
  }

}
