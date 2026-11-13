<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
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

  /**
   * Number of raw moderation candidates checked per database query.
   */
  private const CANDIDATE_BATCH_SIZE = 100;

  /**
   * Maximum raw candidates checked for one page request.
   */
  private const MAX_CANDIDATES_PER_PAGE = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DomainSiteManager $siteManagers,
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
      ->addCacheTags(['aculta_wiki_proposal_list', 'aculta_contribution_list']);

    $position = $this->parseCursor($cursor);
    $candidates = [];
    foreach (['aculta_contribution', 'aculta_wiki_proposal'] as $entityTypeId) {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->addMetaData('account', $this->currentUser)
        ->condition('state', ['submitted', 'needs_changes'], 'IN')
        ->sort('created', 'DESC')
        ->sort('id', 'DESC')
        ->range(0, self::PAGE_SIZE + 1);
      if ($entityTypeId === 'aculta_contribution') {
        $query->condition('source', 'wiki')
          ->condition('source_module', 'aculta_wiki')
          ->condition('purpose', 'wiki');
      }
      if ($position !== NULL) {
        $query->condition($query->orConditionGroup()
          ->condition('created', $position[0], '<')
          ->condition($query->andConditionGroup()
            ->condition('created', $position[0])
            ->condition('id', $position[1], '<')));
      }
      $ids = array_values($query->execute());
      $candidates = [...$candidates, ...array_values($storage->loadMultiple($ids))];
    }
    usort($candidates, function (EntityInterface $left, EntityInterface $right): int {
      $created = $this->createdTimestamp($right) <=> $this->createdTimestamp($left);
      if ($created !== 0) {
        return $created;
      }
      $id = (int) $right->id() <=> (int) $left->id();
      if ($id !== 0) {
        return $id;
      }
      return ($left instanceof Contribution ? 0 : 1) <=> ($right instanceof Contribution ? 0 : 1);
    });
    $unique = [];
    foreach ($candidates as $candidate) {
      $proposalCandidate = $this->proposalCandidate($candidate);
      if ($proposalCandidate === NULL
        || ($proposalCandidate instanceof Contribution && $proposalCandidate->get('source')->value !== 'wiki')) {
        continue;
      }
      $unique[$proposalCandidate->uuid()] ??= $proposalCandidate;
    }
    $items = [];
    foreach ($unique as $proposal) {
      if (!$proposal->isDefaultRevision()
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

    $result = $this->siteManagers->runInSite(SiteKey::Wiki, function () use ($cursor, $cacheability): WikiEditorialQueuePage {
      $wikiDomain = $this->siteManagers->getDomain(SiteKey::Wiki);
      if ($wikiDomain === NULL) {
        return new WikiEditorialQueuePage([], NULL, $cacheability);
      }

      $storage = $this->entityTypeManager->getStorage('node');
      $stateStorage = $this->entityTypeManager->getStorage('content_moderation_state');
      $items = [];
      $scanCursor = $this->parseRevisionCursor($cursor);
      $candidateCount = 0;
      $hasMoreCandidates = FALSE;
      $hasExtraAuthorizedItem = FALSE;

      while ($candidateCount < self::MAX_CANDIDATES_PER_PAGE) {
        $batchLimit = min(
          self::CANDIDATE_BATCH_SIZE,
          self::MAX_CANDIDATES_PER_PAGE - $candidateCount,
        );
        $query = $stateStorage->getAggregateQuery()
          // Core denies access to ContentModerationState. This query discovers
          // candidate VIDs only; every Node is revalidated below.
          ->accessCheck(FALSE)
          ->aggregate('content_entity_revision_id', 'MAX')
          ->groupBy('content_entity_revision_id')
          ->condition('content_entity_type_id', 'node')
          ->condition('workflow', 'wiki_editorial')
          ->condition('moderation_state', 'review')
          ->sort('content_entity_revision_id', 'DESC')
          ->range(0, $batchLimit);
        if ($scanCursor !== NULL) {
          $query->condition('content_entity_revision_id', $scanCursor, '<');
        }

        $revisionIds = array_map('strval', array_values($query->execute()));
        if ($revisionIds === []) {
          $hasMoreCandidates = FALSE;
          break;
        }
        $revisions = $storage->loadMultipleRevisions($revisionIds);
        $hasMoreCandidates = count($revisionIds) === $batchLimit;
        foreach ($revisionIds as $revisionId) {
          $scanCursor = (int) $revisionId;
          $candidateCount++;
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
          if (count($items) > self::PAGE_SIZE) {
            $hasExtraAuthorizedItem = TRUE;
            break 2;
          }
        }

        if (!$hasMoreCandidates) {
          break;
        }
      }

      if ($hasExtraAuthorizedItem) {
        $items = array_slice($items, 0, self::PAGE_SIZE);
        $last = $items[array_key_last($items)];
        $nextCursor = (string) $last->getRevisionId();
      }
      elseif ($hasMoreCandidates && $scanCursor !== NULL) {
        // A bounded scan can return fewer than PAGE_SIZE rows when access
        // filtering removes candidates. Advancing by the last checked VID
        // keeps the next page complete without an unbounded database scan.
        $nextCursor = (string) $scanCursor;
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
   * Gets a created timestamp from a content entity candidate.
   */
  private function createdTimestamp(EntityInterface $entity): int {
    return $entity instanceof ContentEntityInterface ? (int) $entity->get('created')->value : 0;
  }

  /**
   * Narrows a loaded entity to one of the proposal types owned by the Wiki.
   */
  private function proposalCandidate(EntityInterface $entity): WikiProposal|Contribution|null {
    if ($entity instanceof WikiProposal || $entity instanceof Contribution) {
      return $entity;
    }
    return NULL;
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
