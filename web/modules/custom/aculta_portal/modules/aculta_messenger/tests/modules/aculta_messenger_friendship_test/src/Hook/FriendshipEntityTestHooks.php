<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_friendship_test\Hook;

use Drupal\aculta_messenger_friendship_test\Entity\FriendshipFixture;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers the friendship entity fixture without installing aculta_user.
 */
final class FriendshipEntityTestHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(FriendshipFixture::class))->getAttributes(ContentEntityType::class);
    $definition = $attributes[0]->newInstance();
    $definition->setClass(FriendshipFixture::class);
    $definition->setProvider('aculta_messenger_friendship_test');
    $entity_types['aculta_friendship'] = $definition->get();
  }

}
