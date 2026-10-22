<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controls access to the public Forum post submission form.
 */
final class ForumPostSubmissionAccess {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DiscussionContextResolver $discussionContextResolver,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Allows a participant to post in an accessible topic.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $uuid = (string) $this->requestStack->getCurrentRequest()?->query->get('topico', '');
    if (!$account->isAuthenticated() || $uuid === '') {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico', 'url.query_args:resposta']);
    }

    $topicIds = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    if ($topicIds === []) {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico', 'url.query_args:resposta']);
    }

    $topic = $this->entityTypeManager->getStorage('node')->load(reset($topicIds));
    $context = $topic === NULL ? NULL : $this->discussionContextResolver->resolve($topic, $account);
    if ($context === NULL || $context->getType() !== 'public_forum') {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico', 'url.query_args:resposta']);
    }

    $access = $context->getWriteAccess()
      ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico', 'url.query_args:resposta']);
    $parentUuid = (string) $this->requestStack->getCurrentRequest()?->query->get('resposta', '');
    if ($parentUuid === '') {
      return $access;
    }

    $postStorage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $parentIds = $postStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $parentUuid)
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();
    if ($parentIds === []) {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico', 'url.query_args:resposta'])
        ->addCacheTags(['aculta_forum_post_list']);
    }

    $parent = $postStorage->load(reset($parentIds));
    if ($parent === NULL) {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico', 'url.query_args:resposta'])
        ->addCacheTags(['aculta_forum_post_list']);
    }
    $sameTopic = $parent->isPublished()
      && $parent->isDefaultRevision()
      && (string) $parent->get('topic')->target_id === (string) $topic->id();
    $access = $access
      ->andIf($parent->access('view', $account, TRUE))
      ->andIf(AccessResult::allowedIf($sameTopic)->addCacheableDependency($parent))
      ->addCacheableDependency($parent)
      ->addCacheContexts(['url.query_args:resposta'])
      ->addCacheTags(['aculta_forum_post_list']);
    return $access;
  }

}
