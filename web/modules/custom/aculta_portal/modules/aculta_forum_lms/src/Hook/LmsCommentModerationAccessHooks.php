<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Hook;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Grants contextual Educators access to LMS comment moderation operations.
 */
final class LmsCommentModerationAccessHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_context_resolver')]
    private readonly DiscussionContextResolver $contextResolver,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof CommentInterface
      || !in_array($operation, ['view', 'approve'], TRUE)
      || $entity->getFieldName() !== 'field_aculta_discussion') {
      return AccessResult::neutral();
    }

    $host = $entity->getCommentedEntity();
    if ($host === NULL) {
      return AccessResult::neutral()->addCacheableDependency($entity);
    }

    $context = $this->contextResolver->resolve($host, $account);
    if ($context === NULL || $context->getType() !== 'lms') {
      return AccessResult::neutral()->addCacheableDependency($entity);
    }

    $moderation = $context->getModerationAccess()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($context);

    // Educators may see pending/rejected items and use Drupal's approve
    // operation. This does not grant edit/delete access or affect other
    // discussion contexts. Non-Educators keep Core's normal access result.
    return $moderation->isAllowed()
      ? $moderation
      : AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($context);
  }

}
