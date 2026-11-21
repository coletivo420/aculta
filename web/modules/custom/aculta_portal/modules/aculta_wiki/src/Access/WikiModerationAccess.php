<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Access;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\ModerationScope;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * WE Sativa authorization for the Wiki's own editorial and CGU queues.
 *
 * No Forum role, Forum action or Marley privilege is considered here.
 */
final class WikiModerationAccess {

  public function __construct(
    private readonly DomainSiteManagerInterface $sites,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * Whether the authenticated user may use any Wiki moderation surface.
   */
  public function overviewAccess(AccountInterface $account): AccessResultInterface {
    $base = $this->base($account);
    if (!$base->isAllowed()) {
      return $base;
    }
    return $base->andIf(AccessResult::allowedIf(
      $this->canReviewEntries($account)
      || $this->canReviewProposals($account)
      || ModerationScope::allows($account, 'wiki'),
    )->cachePerPermissions());
  }

  public function proposalsAccess(AccountInterface $account): AccessResultInterface {
    return $this->base($account)->andIf(
      AccessResult::allowedIf($this->canReviewProposals($account))->cachePerPermissions(),
    );
  }

  public function revisionsAccess(AccountInterface $account): AccessResultInterface {
    return $this->base($account)->andIf(
      AccessResult::allowedIf($this->canReviewEntries($account))->cachePerPermissions(),
    );
  }

  public function reportsAccess(AccountInterface $account): AccessResultInterface {
    return $this->base($account)->andIf(
      AccessResult::allowedIf(ModerationScope::allows($account, 'wiki'))->cachePerPermissions(),
    );
  }

  /**
   * A Wiki report is readable only if its exact Wiki host is also readable.
   */
  public function reportAccess(AccountInterface $account, ?CguReport $aculta_cgu_report = NULL): AccessResultInterface {
    $result = $this->reportsAccess($account);
    if (!$result->isAllowed() || $aculta_cgu_report === NULL) {
      return $result->andIf(AccessResult::forbidden());
    }
    $evidence = AccessResult::allowed()->addCacheableDependency($aculta_cgu_report);
    $result = $result->andIf($evidence);
    if ($aculta_cgu_report->get('purpose')->getString() !== 'wiki') {
      return $result->andIf(AccessResult::forbidden());
    }
    $type = $aculta_cgu_report->get('target_type')->getString();
    $raw = $aculta_cgu_report->get('target_id')->value;
    if (!in_array($type, ['node', 'comment'], TRUE) || !is_numeric($raw)) {
      return $result->andIf(AccessResult::forbidden());
    }
    $target = $this->entities->getStorage($type)->load((int) $raw);
    if ($type === 'node') {
      $host = $target instanceof NodeInterface ? $target : NULL;
    }
    else {
      $host = $target instanceof CommentInterface ? $target->getCommentedEntity() : NULL;
      if (!$target instanceof CommentInterface
        || $target->bundle() !== 'aculta_discussion_post'
        || $target->getFieldName() !== 'field_aculta_discussion') {
        return $result->andIf(AccessResult::forbidden());
      }
    }
    if (!$host instanceof NodeInterface || !$this->isWikiHost($host)) {
      return $result->andIf(AccessResult::forbidden());
    }

    $hostView = $host->access('view', $account, TRUE);
    $reportView = $aculta_cgu_report->access('view', $account, TRUE);
    $result = $result->andIf(AccessResult::allowed()->addCacheableDependency($host))
      ->andIf($hostView)->andIf($reportView);
    if (!$result->isAllowed()) {
      return $result;
    }
    if ($target instanceof CommentInterface) {
      $targetView = $target->access('view', $account, TRUE);
      $result = $result->andIf(AccessResult::allowed()->addCacheableDependency($target))
        ->andIf($targetView);
      if (!$result->isAllowed()) {
        return $result;
      }
    }
    return $result;
  }

  /**
   * Ensures the host belongs to Wiki and not a cross-domain Wiki clone.
   */
  public function isWikiHost(NodeInterface $host): bool {
    if ($host->bundle() !== 'wiki_entry'
      || !$host->hasField('field_domain_source')
      || $host->get('field_domain_source')->isEmpty()) {
      return FALSE;
    }
    $domain = $host->get('field_domain_source')->getValue();
    $first = is_array($domain) ? ($domain[0] ?? NULL) : NULL;
    $id = is_array($first) ? ($first['target_id'] ?? NULL) : NULL;
    return is_scalar($id)
      && $this->sites->getSiteKeyValueForDomainId((string) $id) === 'wiki';
  }

  /**
   * Authenticated, exact-site baseline shared by all Wiki moderation pages.
   */
  private function base(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf(
      $account->isAuthenticated() && $this->sites->getCurrentSiteKey()?->value === 'wiki',
    )->cachePerUser()->cachePerPermissions()->addCacheContexts(['domain']);
  }

  private function canReviewEntries(AccountInterface $account): bool {
    return $account->hasPermission('edit any wiki_entry content')
      && ($account->hasPermission('use wiki_editorial transition review_to_draft')
        || $account->hasPermission('use wiki_editorial transition review_to_published'));
  }

  private function canReviewProposals(AccountInterface $account): bool {
    return $account->hasPermission('moderate wiki proposals')
      && ($account->hasPermission('view any wiki proposals') || $account->hasPermission('administer wiki proposals'));
  }

}
