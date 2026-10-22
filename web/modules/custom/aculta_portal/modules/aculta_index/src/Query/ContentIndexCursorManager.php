<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;

/**
 * Stores account/query-bound opaque cursors without exposing source IDs.
 */
final class ContentIndexCursorManager {

  /**
   * Cursor lifetime in seconds. */
  private const TTL = 900;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Creates a cursor and returns its random bearer token.
   */
  public function create(int $uid, string $generationId, string $queryHash, int $changed, int $entryId): string {
    $this->validateState($uid, $generationId, $queryHash, $changed, $entryId);
    $token = Crypt::randomBytesBase64(32);
    $now = $this->time->getRequestTime();
    $this->database->insert('aculta_index_cursor')
      ->fields([
        'token_hash' => hash('sha256', $token),
        'uid' => $uid,
        'generation_id' => $generationId,
        'query_hash' => $queryHash,
        'cursor_changed' => $changed,
        'cursor_entry_id' => $entryId,
        'cursor_revision' => 0,
        'expires' => $now + self::TTL,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
    return $token;
  }

  /**
   * Resolves an unexpired cursor only for its owner, query, and generation.
   *
   * @return array{changed: int, entry_id: int, revision: int}
   *   Internal keyset values; never serialize these directly to a consumer.
   */
  public function load(string $token, int $uid, string $generationId, string $queryHash): array {
    $tokenHash = $this->tokenHash($token);
    $cursor = $this->database->select('aculta_index_cursor', 'cursor')
      ->fields('cursor', [
        'uid',
        'generation_id',
        'query_hash',
        'cursor_changed',
        'cursor_entry_id',
        'cursor_revision',
        'expires',
      ])
      ->condition('token_hash', $tokenHash)
      ->execute()
      ->fetchAssoc();
    if (!$cursor || (int) $cursor['expires'] <= $this->time->getRequestTime()) {
      if ($cursor) {
        $this->deleteHash($tokenHash);
      }
      throw new \InvalidArgumentException('Content Index cursor is invalid or expired.');
    }
    if ((int) $cursor['uid'] !== $uid
      || !hash_equals((string) $cursor['generation_id'], $generationId)
      || !hash_equals((string) $cursor['query_hash'], $queryHash)) {
      throw new \InvalidArgumentException('Content Index cursor is invalid or expired.');
    }
    return [
      'changed' => (int) $cursor['cursor_changed'],
      'entry_id' => (int) $cursor['cursor_entry_id'],
      'revision' => (int) $cursor['cursor_revision'],
    ];
  }

  /**
   * Advances a mutable opaque cursor and refreshes its expiry.
   */
  public function advance(string $token, int $uid, string $generationId, string $queryHash, int $changed, int $entryId, int $revision): void {
    $this->validateState($uid, $generationId, $queryHash, $changed, $entryId);
    $tokenHash = $this->tokenHash($token);
    $now = $this->time->getRequestTime();
    $updated = $this->database->update('aculta_index_cursor')
      ->fields([
        'cursor_changed' => $changed,
        'cursor_entry_id' => $entryId,
        'cursor_revision' => $revision + 1,
        'expires' => $now + self::TTL,
        'updated' => $now,
      ])
      ->condition('token_hash', $tokenHash)
      ->condition('uid', $uid)
      ->condition('generation_id', $generationId)
      ->condition('query_hash', $queryHash)
      ->condition('cursor_revision', $revision)
      ->condition('expires', $now, '>')
      ->execute();
    if ($updated !== 1) {
      throw new \InvalidArgumentException('Content Index cursor is invalid or expired.');
    }
  }

  /**
   * Deletes a cursor after its query has reached the end.
   */
  public function delete(string $token, int $uid, string $generationId, string $queryHash): void {
    $this->database->delete('aculta_index_cursor')
      ->condition('token_hash', $this->tokenHash($token))
      ->condition('uid', $uid)
      ->condition('generation_id', $generationId)
      ->condition('query_hash', $queryHash)
      ->execute();
  }

  /**
   * Deletes expired cursors in a bounded cleanup batch.
   */
  public function prune(int $limit = 500): int {
    if ($limit < 1 || $limit > 5000) {
      throw new \InvalidArgumentException('Cursor cleanup limit must be between 1 and 5000.');
    }
    $expired = $this->database->select('aculta_index_cursor', 'cursor')
      ->fields('cursor', ['token_hash'])
      ->condition('expires', $this->time->getRequestTime(), '<=')
      ->orderBy('expires')
      ->range(0, $limit)
      ->execute()
      ->fetchCol();
    if ($expired === []) {
      return 0;
    }
    return $this->database->delete('aculta_index_cursor')
      ->condition('token_hash', $expired, 'IN')
      ->condition('expires', $this->time->getRequestTime(), '<=')
      ->execute();
  }

  /**
   * Validates identity and keyset values before persisting cursor state. */
  private function validateState(int $uid, string $generationId, string $queryHash, int $changed, int $entryId): void {
    if ($uid < 0 || $generationId === '' || strlen($generationId) > 64 || preg_match('/^[a-f0-9]{64}$/', $queryHash) !== 1 || $changed < 0 || $entryId < 1) {
      throw new \InvalidArgumentException('Content Index cursor state is invalid.');
    }
  }

  /**
   * Hashes a well-formed URL-safe random token. */
  private function tokenHash(string $token): string {
    if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
      throw new \InvalidArgumentException('Content Index cursor is invalid or expired.');
    }
    return hash('sha256', $token);
  }

  /**
   * Deletes an already hashed token. */
  private function deleteHash(string $tokenHash): void {
    $this->database->delete('aculta_index_cursor')
      ->condition('token_hash', $tokenHash)
      ->execute();
  }

}
