<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Validates whether a user can react to a comment, forum post, or forum topic.
 */
class DiscussionReactionAccess {

  public function __construct(
    protected readonly DiscussionContextResolver $contextResolver,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks whether the account can react to the target entity.
   */
  public function checkAccess(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    $config = $this->configFactory->get('aculta_forum.settings');
    if (!$config->get('reactions_enabled')) {
      return AccessResult::forbidden('Reactions are disabled globally.')->addCacheableDependency($config);
    }

    if (!$account->isAuthenticated()) {
      return AccessResult::forbidden('Anonymous users cannot react.')->addCacheContexts(['user.roles:authenticated']);
    }

    if (!$account->hasPermission('react to aculta discussions')) {
      return AccessResult::forbidden('User does not have permission to react.')->cachePerPermissions();
    }

    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;
    if ($authorUid > 0 && $authorUid === (int) $account->id()) {
      return AccessResult::forbidden('Users cannot react to their own content.')->addCacheableDependency($entity)->addCacheContexts(['user']);
    }

    // Resolve host entity and check status.
    if ($entity instanceof CommentInterface) {
      if (!$entity->isPublished()) {
        return AccessResult::forbidden('Comment is not published.')->addCacheableDependency($entity);
      }
      $host = $entity->getCommentedEntity();
    }
    elseif ($entity instanceof ForumPost) {
      if (!$entity->isPublished()) {
        return AccessResult::forbidden('Forum post is not published.')->addCacheableDependency($entity);
      }
      $topic = $entity->get('topic')->entity;
      $host = $topic;
    }
    elseif ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic') {
      if (!$entity->isPublished()) {
        return AccessResult::forbidden('Forum topic is not published.')->addCacheableDependency($entity);
      }
      $host = $entity;
    }
    else {
      return AccessResult::forbidden('Target entity type is not supported for reactions.');
    }

    if ($host === NULL) {
      return AccessResult::forbidden('Host discussion entity not found.')->addCacheableDependency($entity);
    }
    if (!$host instanceof FieldableEntityInterface) {
      return AccessResult::forbidden('Host discussion entity cannot carry discussion fields.')
        ->addCacheableDependency($entity)
        ->addCacheableDependency($host);
    }

    $context = $this->contextResolver->resolve($host, $account);
    if ($context === NULL) {
      return AccessResult::forbidden('Host does not have a registered discussion context.')->addCacheableDependency($host);
    }

    $readAccess = $context->getReadAccess();
    if (!$readAccess->isAllowed()) {
      return AccessResult::forbidden('Read access denied to discussion.')->addCacheableDependency($readAccess);
    }

    // Check if discussion is open if host has discussion field.
    if ($host->hasField('field_aculta_discussion') && !$host->get('field_aculta_discussion')->isEmpty()) {
      $status = (int) $host->get('field_aculta_discussion')->status;
      if ($status !== CommentingStatus::Open->value) {
        return AccessResult::forbidden('Discussion is closed.')->addCacheableDependency($host);
      }
    }

    return AccessResult::allowed()
      ->addCacheableDependency($config)
      ->addCacheableDependency($entity)
      ->addCacheableDependency($host)
      ->addCacheableDependency($readAccess)
      ->cachePerPermissions()
      ->addCacheContexts(['user']);
  }

}
