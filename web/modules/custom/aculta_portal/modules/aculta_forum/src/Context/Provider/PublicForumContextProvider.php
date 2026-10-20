<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Context\Provider;

use Drupal\aculta_forum\Context\DiscussionContext;
use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides contexts only for independent public Forum topic nodes.
 */
final class PublicForumContextProvider implements DiscussionContextProviderInterface {

  public function __construct(private readonly DomainPurposeManager $domainPurposeManager) {}

  /**
   * Checks whether an entity is an independent public Forum topic.
   */
  public function supports(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic';
  }

  /**
   * Builds the public Forum context and applies purpose-aware read access.
   */
  public function getContext(EntityInterface $entity, AccountInterface $account): DiscussionContextInterface {
    if (!$this->supports($entity)) {
      throw new \LogicException('Public forum context requires an aculta_forum_topic node.');
    }
    if ($entity->isNew()) {
      throw new \LogicException('A discussion context requires a persisted host entity.');
    }
    $uuid = $entity->uuid();
    if ($uuid === NULL || $uuid === '') {
      throw new \LogicException('A discussion host must have a stable UUID.');
    }

    $forumPurposeAccess = AccessResult::allowedIf($this->domainPurposeManager->getCurrentPurpose() === 'forum')
      ->addCacheContexts(['domain']);
    $readAccess = $entity->access('view', $account, TRUE)->andIf($forumPurposeAccess);
    $neutralWrite = AccessResult::neutral()->addCacheableDependency($entity);
    $neutralModeration = AccessResult::neutral()->addCacheableDependency($entity);
    return new DiscussionContext(
      $entity,
      'public_forum',
      'public_forum:node:' . $uuid,
      $readAccess,
      $neutralWrite,
      $neutralModeration,
      $entity->toUrl('canonical'),
      'public_if_entity_accessible',
      'public_if_entity_accessible',
      'public_if_entity_accessible',
    );
  }

}
