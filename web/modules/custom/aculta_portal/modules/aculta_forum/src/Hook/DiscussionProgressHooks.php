<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Attaches progressive navigation to rendered shared discussions.
 */
final class DiscussionProgressHooks {

  /**
   * Marks the shared discussion field for its client-side reading controls.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof NodeInterface
      || ($build['#view_mode'] ?? NULL) !== 'full'
      || !$entity->hasField('field_aculta_discussion')
      || !isset($build['field_aculta_discussion'])
      || (isset($build['field_aculta_discussion']['#access']) && $build['field_aculta_discussion']['#access'] === FALSE)) {
      return;
    }

    $build['field_aculta_discussion']['#attributes']['data-aculta-discussion-thread'] = '';
    $build['field_aculta_discussion']['#attached']['library'][] = 'aculta_forum/discussion-progress';
  }

}
