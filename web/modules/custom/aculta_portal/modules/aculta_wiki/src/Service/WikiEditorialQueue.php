<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Queries private Wiki proposals for the authorized editorial queue.
 */
final class WikiEditorialQueue {

  private const PAGE_SIZE = 25;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly StateTransitionValidationInterface $transitionValidation,
  ) {}

  /**
   * Returns a stable page of pending proposals.
   *
   * The cursor is a position only. It never grants access to a proposal; every
   * loaded entity is revalidated against current view and moderation access.
   *
   * @param string|null $cursor
   *   Optional timestamp-ID cursor from the previous page.
   */
  public function proposals(?string $cursor = NULL): WikiEditorialQueuePage {
    if (!$this->currentUser->hasPermission('moderate wiki proposals')) {
      throw new AccessDeniedHttpException();
    }

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.permissions', 'url.query_args:wiki_proposal_cursor'])
      ->addCacheTags(['aculta_wiki_proposal_list']);
    $storage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->addMetaData('account', $this->currentUser)
      ->condition('state', ['submitted', 'needs_changes'], 'IN')
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, self::PAGE_SIZE + 1);

    $position = $this->parseCursor($cursor);
    if ($position !== NULL) {
      $after = $query->orConditionGroup()
        ->condition('created', $position[0], '<')
        ->condition($query->andConditionGroup()
          ->condition('created', $position[0])
          ->condition('id', $position[1], '<'));
      $query->condition($after);
    }

    $ids = array_values($query->execute());
    $loaded = $storage->loadMultiple($ids);
    $items = [];
    foreach ($ids as $id) {
      $proposal = $loaded[$id] ?? NULL;
      if (!$proposal instanceof WikiProposal
        || !$proposal->isDefaultRevision()
        || !in_array($proposal->get('state')->value, ['submitted', 'needs_changes'], TRUE)) {
        continue;
      }
      $viewAccess = $proposal->access('view', $this->currentUser, TRUE);
      $moderateAccess = $proposal->access('update', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($proposal)
        ->addCacheableDependency($viewAccess)
        ->addCacheableDependency($moderateAccess);
      if (!$viewAccess->isAllowed() || !$moderateAccess->isAllowed()) {
        continue;
      }
      $items[] = $proposal;
    }

    $hasMore = count($items) > self::PAGE_SIZE;
    if ($hasMore) {
      $items = array_slice($items, 0, self::PAGE_SIZE);
      $last = $items[array_key_last($items)];
      $nextCursor = $last->get('created')->value . '-' . $last->id();
    }
    else {
      $nextCursor = NULL;
    }

    return new WikiEditorialQueuePage($items, $nextCursor, $cacheability);
  }

  /**
   * Returns pending Node revisions that the editor can review in Wiki purpose.
   *
   * Only the latest revision of each `wiki_entry` can appear. Every result is
   * checked for view, view-revision, update, and the current Wiki workflow
   * transition before it is returned.
   *
   * @param string|null $cursor
   *   Optional revision-timestamp-ID cursor from the previous page.
   */
  public function entryRevisions(?string $cursor = NULL): WikiEditorialQueuePage {
    $canEditAny = $this->currentUser->hasPermission('edit any wiki_entry content');
    $canTransition = $this->currentUser->hasPermission('use wiki_editorial transition review_to_draft')
      || $this->currentUser->hasPermission('use wiki_editorial transition review_to_published');
    if (!$canEditAny || !$canTransition) {
      throw new AccessDeniedHttpException();
    }

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts([
        'domain',
        'user',
        'user.permissions',
        'user.node_grants:view',
        'url.query_args:wiki_revision_cursor',
      ])
      ->addCacheTags(['node_list:wiki_entry', 'content_moderation_state_list']);

    $result = $this->domainPurposes->runInPurpose('wiki', function () use ($cursor, $cacheability): WikiEditorialQueuePage {
      $wikiDomain = $this->domainPurposes->getDomain('wiki');
      if ($wikiDomain === NULL) {
        return new WikiEditorialQueuePage([], NULL, $cacheability);
      }

      $stateStorage = $this->entityTypeManager->getStorage('content_moderation_state');
      $query = $stateStorage->getAggregateQuery()
        // ContentModerationState is intentionally denied entity access by
        // Core. The aggregate query discovers candidate VIDs only; below, each
        // Node revision is revalidated under Wiki purpose and current access.
        ->accessCheck(FALSE)
        ->aggregate('content_entity_id', 'MAX')
        ->groupBy('content_entity_revision_id')
        ->condition('content_entity_type_id', 'node')
        ->condition('workflow', 'wiki_editorial')
        ->condition('moderation_state', 'review')
        ->sort('content_entity_revision_id', 'DESC')
        ->range(0, self::PAGE_SIZE + 1);
      $revisionCursor = $this->parseRevisionCursor($cursor);
      if ($revisionCursor !== NULL) {
        $query->condition('content_entity_revision_id', $revisionCursor, '<');
      }

      $queryRows = $query->execute();
      $revisionIds = array_map('strval', array_column($queryRows, 'content_entity_revision_id'));
      $storage = $this->entityTypeManager->getStorage('node');
      $revisions = $revisionIds === [] ? [] : $storage->loadMultipleRevisions($revisionIds);
      $items = [];
      foreach ($revisionIds as $revisionId) {
        $revision = $revisions[$revisionId] ?? NULL;
        if (!$revision instanceof NodeInterface
          || (string) $storage->getLatestRevisionId($revision->id()) !== $revisionId
          || $revision->bundle() !== 'wiki_entry'
          || !$revision->hasField('field_domain_source')
          || $revision->get('field_domain_source')->target_id !== $wikiDomain->id()
          || !$revision->hasField('moderation_state')
          || $revision->get('moderation_state')->value !== 'review') {
          continue;
        }

        $viewAccess = $revision->access('view', $this->currentUser, TRUE);
        $revisionAccess = $revision->access('view revision', $this->currentUser, TRUE);
        $updateAccess = $revision->access('update', $this->currentUser, TRUE);
        $transitions = $this->transitionValidation->getValidTransitions($revision, $this->currentUser);
        $cacheability->addCacheableDependency($revision)
          ->addCacheableDependency($viewAccess)
          ->addCacheableDependency($revisionAccess)
          ->addCacheableDependency($updateAccess);
        if (!$viewAccess->isAllowed() || !$revisionAccess->isAllowed() || !$updateAccess->isAllowed() || $transitions === []) {
          continue;
        }
        $items[] = $revision;
      }

      $hasMore = count($items) > self::PAGE_SIZE;
      if ($hasMore) {
        $items = array_slice($items, 0, self::PAGE_SIZE);
        $last = $items[array_key_last($items)];
        $nextCursor = (string) $last->getRevisionId();
      }
      else {
        $nextCursor = NULL;
      }
      return new WikiEditorialQueuePage($items, $nextCursor, $cacheability);
    });

    return $result instanceof WikiEditorialQueuePage
      ? $result
      : new WikiEditorialQueuePage([], NULL, $cacheability);
  }

  /**
   * Parses a timestamp-ID cursor or ignores malformed input.
   *
   * @return array{0: int, 1: int}|null
   *   The timestamp and proposal ID position.
   */
  private function parseCursor(?string $cursor): ?array {
    if (!is_string($cursor) || !preg_match('/^([0-9]{1,12})-([1-9][0-9]{0,12})$/D', $cursor, $parts)) {
      return NULL;
    }
    return [(int) $parts[1], (int) $parts[2]];
  }

  /**
   * Parses a revision ID cursor.
   *
   * @return int|null
   *   A positive revision ID or NULL for malformed input.
   */
  private function parseRevisionCursor(?string $cursor): ?int {
    if (!is_string($cursor) || !preg_match('/^[1-9][0-9]{0,12}$/D', $cursor)) {
      return NULL;
    }
    return (int) $cursor;
  }

}
