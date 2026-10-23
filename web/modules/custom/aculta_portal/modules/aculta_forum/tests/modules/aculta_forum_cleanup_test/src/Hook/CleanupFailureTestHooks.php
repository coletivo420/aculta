<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_cleanup_test\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Blocks Forum attachment deletion while the test flag is enabled.
 */
final class CleanupFailureTestHooks {

  /**
   * State key that enables the simulated failure.
   */
  public const STATE_KEY = 'aculta_forum_cleanup_test.fail_media_delete';

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() === 'media'
      && $entity->bundle() === 'forum_attachment'
      && (bool) \Drupal::state()->get(self::STATE_KEY, FALSE)) {
      throw new \RuntimeException('Simulated Forum attachment deletion failure.');
    }
  }

}
