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
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico']);
    }

    $topicIds = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    if ($topicIds === []) {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico']);
    }

    $topic = $this->entityTypeManager->getStorage('node')->load(reset($topicIds));
    $context = $topic === NULL ? NULL : $this->discussionContextResolver->resolve($topic, $account);
    if ($context === NULL || $context->getType() !== 'public_forum') {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico']);
    }

    return $context->getWriteAccess()
      ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args:topico']);
  }

}
