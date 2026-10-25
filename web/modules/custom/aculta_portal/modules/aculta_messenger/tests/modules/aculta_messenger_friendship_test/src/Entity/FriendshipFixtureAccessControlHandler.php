<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_friendship_test\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Applies participant access in the isolated relationship fixture. */
final class FriendshipFixtureAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc} */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($entity instanceof FriendshipFixture
      && in_array($operation, ['view', 'update'], TRUE)
      && in_array((int) $account->id(), [$entity->getMemberLowId(), $entity->getMemberHighId()], TRUE)) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden()->cachePerUser();
  }

}
