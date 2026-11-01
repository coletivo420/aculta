<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Hook;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Grants LMS educators contextual access to the discussion moderation marker.
 */
final class LmsDiscussionModerationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_context_resolver')]
    private readonly DiscussionContextResolver $contextResolver,
  ) {}

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() !== 'field_aculta_rejected'
      || !in_array($operation, ['view', 'edit'], TRUE)
      || !$items?->getEntity() instanceof CommentInterface) {
      return AccessResult::neutral();
    }

    $comment = $items->getEntity();
    $host = $comment->getCommentedEntity();
    if ($host === NULL) {
      return AccessResult::forbidden()->addCacheableDependency($comment);
    }

    $context = $this->contextResolver->resolve($host, $account);
    if ($context === NULL || $context->getType() !== 'lms') {
      return AccessResult::neutral();
    }

    return $context->getModerationAccess()
      ->addCacheableDependency($comment);
  }

}
