<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Reads only explicit Drupal entity ownership for internal Index filtering.
 */
final class ContentIndexOwnerResolver {

  /**
   * Returns a positive owner UID when the source declares an owner.
   */
  public static function resolve(ContentEntityInterface $entity): ?int {
    if (!$entity instanceof EntityOwnerInterface) {
      return NULL;
    }
    $ownerId = $entity->getOwnerId();
    if (is_string($ownerId) && preg_match('/^(0|[1-9][0-9]{0,9})$/', $ownerId) !== 1) {
      return NULL;
    }
    if (!is_int($ownerId) && !is_string($ownerId)) {
      return NULL;
    }
    $uid = (int) $ownerId;
    return $uid >= 0 && $uid <= 4_294_967_295 ? $uid : NULL;
  }

}
