<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

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
 * Restricts the rejection marker to comment administrators or contextual moderators.
 */
final class DiscussionModerationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_context_resolver')]
    private readonly ?DiscussionContextResolver $contextResolver = NULL,
  ) {}

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() !== 'field_aculta_rejected') {
      return AccessResult::neutral();
    }

    if ($account->hasPermission('administer comments')) {
      return AccessResult::allowed()->addCacheContexts(['user.permissions']);
    }

    // Contextual moderation check for comments in hosted discussions (e.g. LMS).
    if (in_array($operation, ['view', 'edit'], TRUE)
      && $items?->getEntity() instanceof CommentInterface
      && $this->contextResolver !== NULL) {
      $comment = $items->getEntity();
      $host = $comment->getCommentedEntity();
      if ($host !== NULL) {
        $context = $this->contextResolver->resolve($host, $account);
        if ($context !== NULL && $context->getModerationAccess()->isAllowed()) {
          return $context->getModerationAccess()->addCacheableDependency($comment);
        }
      }
    }

    return AccessResult::forbidden()->addCacheContexts(['user.permissions']);
  }

}
