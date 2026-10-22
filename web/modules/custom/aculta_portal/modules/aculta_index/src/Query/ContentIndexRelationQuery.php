<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Queries one named outgoing relation and rechecks source and target access.
 */
final class ContentIndexRelationQuery {

  /**
   * Maximum target candidates checked for one request.
   */
  private const MAX_CANDIDATES = 100;

  public function __construct(
    private readonly Connection $database,
    private readonly ContentIndexGenerationManager $generations,
    private readonly ContentIndexAccessFilter $accessFilter,
    private readonly ContentIndexCursorManager $cursors,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Gets targets reachable through one named, outgoing relation.
   *
   * Both the source and every target are reloaded and checked with Entity
   * Access. Only targets present in the active catalog generation are returned.
   * No totals or facets are calculated.
   *
   * @param string $sourceEntityType
   *   Source entity type ID.
   * @param string $sourceEntityId
   *   Source entity ID.
   * @param string $sourceLangcode
   *   Exact indexed source translation language.
   * @param string $relationType
   *   Explicit relation type declared by the source provider.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account for source/target access checks and cursor ownership.
   * @param string|null $cursor
   *   Opaque continuation token from a prior call with identical arguments.
   * @param int $limit
   *   Maximum target candidates, from 1 through 100.
   */
  public function queryOutgoing(
    string $sourceEntityType,
    string $sourceEntityId,
    string $sourceLangcode,
    string $relationType,
    AccountInterface $account,
    ?string $cursor = NULL,
    int $limit = 25,
  ): ContentIndexQueryResult {
    $this->validateIdentity($sourceEntityType, $sourceEntityId, $sourceLangcode, $relationType);
    if ($limit < 1 || $limit > self::MAX_CANDIDATES) {
      throw new \InvalidArgumentException(sprintf('Content Index relation candidate limit must be between 1 and %d.', self::MAX_CANDIDATES));
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheMaxAge(0);
    $generationId = $this->generations->getActiveGeneration();
    if ($generationId === NULL) {
      return new ContentIndexQueryResult([], NULL, $cacheability);
    }

    $sourceCandidate = [
      [
        'entity_type' => $sourceEntityType,
        'entity_id' => $sourceEntityId,
        'langcode' => $sourceLangcode,
      ],
    ];
    $sourceAccess = $this->accessFilter->filter($sourceCandidate, $account);
    $cacheability = $cacheability->merge($sourceAccess->cacheability);
    if (count($sourceAccess->entities) !== 1) {
      return new ContentIndexQueryResult([], NULL, $cacheability);
    }

    $queryIdentity = [
      'source_entity_type' => $sourceEntityType,
      'source_entity_id' => $sourceEntityId,
      'source_langcode' => $sourceLangcode,
      'relation_type' => $relationType,
      'target_langcode' => $this->languageManager->getDefaultLanguage()->getId(),
    ];
    $queryHash = hash('sha256', json_encode($queryIdentity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $uid = (int) $account->id();
    $after = $cursor === NULL ? NULL : $this->cursors->load($cursor, $uid, $generationId, $queryHash);
    $candidates = $this->loadTargets($generationId, $queryIdentity, $after, $limit);
    $targetAccess = $this->accessFilter->filter($candidates, $account);
    $cacheability = $cacheability->merge($targetAccess->cacheability);

    $nextCursor = NULL;
    if (count($candidates) === $limit) {
      $last = end($candidates);
      if ($cursor === NULL) {
        // The cursor's integer key slot holds relation_id for this query kind.
        $nextCursor = $this->cursors->create($uid, $generationId, $queryHash, 0, $last['relation_id']);
      }
      else {
        $this->cursors->advance($cursor, $uid, $generationId, $queryHash, 0, $last['relation_id'], $after['revision']);
        $nextCursor = $cursor;
      }
    }
    elseif ($cursor !== NULL) {
      $this->cursors->delete($cursor, $uid, $generationId, $queryHash);
    }

    return new ContentIndexQueryResult($targetAccess->entities, $nextCursor, $cacheability);
  }

  /**
   * Loads current-generation target identities in descending relation order.
   *
   * @param string $generationId
   *   Active generation selected by the generation pointer.
   * @param array{source_entity_type: string, source_entity_id: string, source_langcode: string, relation_type: string, target_langcode: string} $identity
   *   Validated query identity and default target language.
   * @param array{changed: int, entry_id: int, revision: int}|null $after
   *   Persisted private keyset state.
   * @param int $limit
   *   Maximum number of candidates to return.
   *
   * @return list<array{entity_type: string, entity_id: string, langcode: string, relation_id: int}>
   *   Candidate targets, not yet authorized.
   */
  private function loadTargets(string $generationId, array $identity, ?array $after, int $limit): array {
    $query = $this->database->select('aculta_index_relation', 'relation')
      ->fields('target', ['entity_type', 'entity_id', 'langcode']);
    $query->addField('relation', 'relation_id', 'relation_id');
    $query->innerJoin(
      'aculta_index_entry',
      'source',
      'relation.generation_id = source.generation_id'
        . ' AND relation.provider_id = source.provider_id'
        . ' AND relation.source_entity_type = source.entity_type'
        . ' AND relation.source_entity_id = source.entity_id'
        . ' AND relation.source_langcode = source.langcode',
    );
    $query->innerJoin(
      'aculta_index_entry',
      'target',
      'relation.generation_id = target.generation_id'
        . ' AND relation.target_entity_type = target.entity_type'
        . ' AND relation.target_entity_id = target.entity_id'
        . ' AND target.langcode = CASE WHEN relation.target_langcode = :index_und THEN :default_langcode ELSE relation.target_langcode END',
      [':index_und' => 'und', ':default_langcode' => $identity['target_langcode']],
    );
    $query->condition('relation.generation_id', $generationId)
      ->condition('relation.source_entity_type', $identity['source_entity_type'])
      ->condition('relation.source_entity_id', $identity['source_entity_id'])
      ->condition('relation.source_langcode', $identity['source_langcode'])
      ->condition('relation.relation_type', $identity['relation_type']);
    if ($after !== NULL) {
      $query->condition('relation.relation_id', $after['entry_id'], '<');
    }
    $rows = $query->distinct()
      ->orderBy('relation.relation_id', 'DESC')
      ->range(0, $limit)
      ->execute();

    $candidates = [];
    foreach ($rows as $row) {
      $candidates[] = [
        'entity_type' => (string) $row->entity_type,
        'entity_id' => (string) $row->entity_id,
        'langcode' => (string) $row->langcode,
        'relation_id' => (int) $row->relation_id,
      ];
    }
    return $candidates;
  }

  /**
   * Rejects malformed entity identities and relation names before querying.
   */
  private function validateIdentity(string $entityType, string $entityId, string $langcode, string $relationType): void {
    if (preg_match('/^[a-z][a-z0-9_]*$/', $entityType) !== 1 || strlen($entityType) > 32
      || $entityId === '' || strlen($entityId) > 255
      || $langcode === '' || strlen($langcode) > 12
      || preg_match('/^[a-z][a-z0-9_]*$/', $relationType) !== 1 || strlen($relationType) > 64) {
      throw new \InvalidArgumentException('Content Index relation query identity is invalid.');
    }
  }

}
