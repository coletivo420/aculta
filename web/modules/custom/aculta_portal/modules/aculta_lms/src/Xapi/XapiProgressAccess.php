<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Checks ownership of an ACULTA course-progress record for xAPI tokens.
 */
final class XapiProgressAccess {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns whether the requested ACULTA progress record belongs to a user.
   */
  public function isOwnedBy(int $progressId, int $uid): bool {
    if ($progressId <= 0 || $uid <= 0) {
      return FALSE;
    }

    $progress = $this->entityTypeManager
      ->getStorage('aculta_lms_progress')
      ->load($progressId);
    if (!$progress instanceof ContentEntityInterface
      || $progress->getEntityTypeId() !== 'aculta_lms_progress'
      || !$progress->hasField('uid')) {
      return FALSE;
    }

    $values = $progress->get('uid')->getValue();
    return isset($values[0]['value']) && (int) $values[0]['value'] === $uid;
  }

}
