<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves and authorizes editing a published public Forum post.
 */
final class ForumPostPublicEditAccess {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Checks whether the current author may edit the requested post.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $post = $this->getPost();
    $contexts = ['domain', 'user', 'user.permissions', 'url.query_args:post'];
    if ($post === NULL) {
      return AccessResult::forbidden()->addCacheContexts($contexts);
    }

    $isOwnPublishedDefault = $account->isAuthenticated()
      && (string) $post->getOwnerId() === (string) $account->id()
      && $post->isPublished()
      && $post->isDefaultRevision();
    $contextAccess = AccessResult::allowedIf($isOwnPublishedDefault)
      ->addCacheableDependency($post)
      ->addCacheContexts($contexts);
    return $contextAccess->andIf($post->access('update', $account, TRUE));
  }

  /**
   * Loads the post identified by the current query string UUID.
   */
  public function getPost(): ?ForumPost {
    $uuid = (string) $this->requestStack->getCurrentRequest()?->query->get('post', '');
    if ($uuid === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }

    $post = $storage->load(reset($ids));
    return $post instanceof ForumPost ? $post : NULL;
  }

}
