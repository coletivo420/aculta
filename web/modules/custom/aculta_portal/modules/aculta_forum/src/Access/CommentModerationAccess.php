<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authorizes moderation of a particular current discussion comment.
 *
 * A forum-wide grant never grants moderation of Wiki, Magazine or LMS hosts.
 * The host, comment, origin and current site are all checked on every request.
 */
final class CommentModerationAccess {

  public function __construct(
    private readonly DomainSiteManagerInterface $sites,
    private readonly DiscussionOriginResolverInterface $origins,
    private readonly DiscussionContextResolver $contexts,
  ) {}

  /**
   * Route access: the HX header is a presentation constraint, not authority.
   */
  public function access(AccountInterface $account, Request $request, CommentInterface $comment): AccessResultInterface {
    $htmx = AccessResult::allowedIf($request->headers->has('HX-Request'))
      ->addCacheContexts(['headers:HX-Request']);
    return $htmx->andIf($this->canModerate($comment, $account));
  }

  /**
   * Context-aware authorization reusable by Form API at submit time.
   *
   * Permission alone is insufficient. Entity Access and the registered host
   * context are evaluated with the current account in the actual site.
   */
  public function canModerate(CommentInterface $comment, AccountInterface $account): AccessResultInterface {
    $denied = AccessResult::forbidden()
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheContexts(['domain'])
      ->addCacheableDependency($comment);

    if (!$account->isAuthenticated()
      || $comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== 'field_aculta_discussion'
      || $comment->getCommentedEntityTypeId() !== 'node') {
      return $denied;
    }

    $host = $comment->getCommentedEntity();
    if (!$host instanceof NodeInterface) {
      return $denied;
    }
    $denied->addCacheableDependency($host);

    $forumModerator = $account->hasPermission('moderate aculta forum comments');
    $globalModerator = $account->hasPermission('administer comments');
    if (!$forumModerator && !$globalModerator) {
      return $denied;
    }

    $currentSite = $this->sites->getCurrentSiteKey()?->value;
    $bundle = $host->bundle();
    $isForum = $bundle === 'aculta_forum_topic';
    $expectedSite = match ($bundle) {
      'aculta_forum_topic' => 'forum',
      'wiki_entry' => 'wiki',
      'revista' => 'magazine',
      'aculta_lms_topic' => 'courses',
      default => NULL,
    };

    // Fail closed if the route is visited from a different site. A Forum-only
    // moderator must never acquire contextual moderation in other domains.
    if ($expectedSite === NULL || $currentSite !== $expectedSite
      || (!$isForum && !$globalModerator)) {
      return $denied;
    }

    $origin = $this->origins->classify($comment);
    $denied->addCacheableDependency($origin);
    if ($origin->origin !== ($isForum ? DiscussionOrigin::CURRENT : DiscussionOrigin::CONTEXTUAL)) {
      return $denied;
    }

    if ($isForum) {
      if (!$host->hasField('field_domain_source')
        || $host->get('field_domain_source')->isEmpty()) {
        return $denied;
      }
      $domain = $host->get('field_domain_source')->getValue();
      $domainId = is_array($domain) && isset($domain[0]['target_id'])
        && is_scalar($domain[0]['target_id'])
        ? (string) $domain[0]['target_id']
        : '';
      if ($domainId === '' || $this->sites->getSiteKeyValueForDomainId($domainId) !== 'forum') {
        return $denied;
      }
    }

    // Drupal node grants cover restricted Groups and revoked memberships.
    $hostView = $host->access('view', $account, TRUE);
    $commentView = $comment->access('view', $account, TRUE);
    $denied->addCacheableDependency($hostView)->addCacheableDependency($commentView);
    if (!$hostView->isAllowed() || !$commentView->isAllowed()) {
      return $denied;
    }

    try {
      $context = $this->contexts->resolve($host, $account);
    }
    catch (\LogicException) {
      // An ambiguous host provider cannot grant access.
      return $denied;
    }
    if ($context === NULL) {
      return $denied;
    }

    $read = $context->getReadAccess();
    $denied->addCacheableDependency($context)->addCacheableDependency($read);
    if (!$read->isAllowed()) {
      return $denied;
    }

    if (!$isForum) {
      // Only global Comment administrators retain contextual moderation
      // when the owner provider explicitly grants it (especially LMS Group).
      $moderation = $context->getModerationAccess();
      $denied->addCacheableDependency($moderation);
      if (!$moderation->isAllowed()) {
        return $denied;
      }
    }

    return AccessResult::allowed()
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheContexts(['domain'])
      ->addCacheableDependency($comment)
      ->addCacheableDependency($host)
      ->addCacheableDependency($origin)
      ->addCacheableDependency($context)
      ->addCacheableDependency($hostView)
      ->addCacheableDependency($commentView);
  }

}
