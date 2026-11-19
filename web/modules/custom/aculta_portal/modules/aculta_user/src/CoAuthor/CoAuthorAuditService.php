<?php

declare(strict_types=1);

namespace Drupal\aculta_user\CoAuthor;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Audit recorder for ACULTA node co-authorship changes.
 *
 * Stores minimal metadata (actor, node, action, timestamp, coauthor UID)
 * with strict privacy: no personal information, emails or private notes.
 */
class CoAuthorAuditService {

  public const TABLE = 'aculta_coauthor_audit';

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Records a coauthorship modification event.
   *
   * @param int $nid
   *   The node ID.
   * @param int $actorUid
   *   The user ID performing the action.
   * @param string $action
   *   Action performed (e.g. 'added', 'removed', 'updated').
   * @param int[] $coauthorUids
   *   The affected or resulting coauthor UIDs.
   */
  public function record(int $nid, int $actorUid, string $action, array $coauthorUids): void {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return;
    }

    $timestamp = $this->time->getRequestTime();
    $uidsString = implode(',', array_unique(array_filter($coauthorUids, static fn(int $id): bool => $id > 0)));

    $this->database->insert(self::TABLE)
      ->fields([
        'nid' => $nid,
        'actor_uid' => $actorUid,
        'action' => substr($action, 0, 32),
        'created' => $timestamp,
        'coauthors' => $uidsString,
      ])
      ->execute();
  }

}
