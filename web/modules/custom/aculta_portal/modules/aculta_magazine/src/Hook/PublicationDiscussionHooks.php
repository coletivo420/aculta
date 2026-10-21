<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\comment\CommentingStatus;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Applies the configured open default to existing publications with no status. */
final class PublicationDiscussionHooks {

  private const FIELD = 'field_aculta_discussion';

  /**
   * Implements hook_ENTITY_TYPE_load() for Nodes.
   *
   * A newly attached Comment field has no stored item on older publication
   * Nodes. Default field values are applied on entity creation, not on load,
   * so supply the configured default in memory when no status was persisted.
   * Explicit Hidden/Closed values and comment statistics remain untouched.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The loaded nodes.
   */
  #[Hook('node_load')]
  public function nodeLoad(array $nodes): void {
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface ||
        !in_array($node->bundle(), ['revista', 'article', 'blog'], TRUE) ||
        !$node->hasField(self::FIELD)) {
        continue;
      }

      $discussion = $node->get(self::FIELD);
      $values = $discussion->getValue();
      $item = $values[0] ?? [];
      if (!array_key_exists('status', $item) || $item['status'] === NULL) {
        // Preserve CommentStatistics values added by Core's
        // entity_storage_load hook for an existing thread.
        $field_default = $node->getFieldDefinition(self::FIELD)->getDefaultValue($node);
        $item['status'] = $field_default[0]['status'] ?? CommentingStatus::Open->value;
        $discussion->setValue([$item]);
      }
    }
  }

}
