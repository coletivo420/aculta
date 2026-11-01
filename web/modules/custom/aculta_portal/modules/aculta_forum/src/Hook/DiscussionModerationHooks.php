<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Restricts the rejection marker to authorized comment moderators.
 */
final class DiscussionModerationHooks {

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResult {
    if ($field_definition->getName() !== 'field_aculta_rejected') {
      return AccessResult::neutral();
    }

    $access = $account->hasPermission('administer comments')
      ? AccessResult::allowed()
      : AccessResult::forbidden();

    return $access->addCacheContexts(['user.permissions']);
  }

}
