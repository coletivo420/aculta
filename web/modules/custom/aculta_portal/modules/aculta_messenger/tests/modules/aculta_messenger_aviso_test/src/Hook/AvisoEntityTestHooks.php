<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_aviso_test\Hook;

use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers the real Aviso definition without enabling Messenger integrations.
 */
final class AvisoEntityTestHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(Aviso::class))->getAttributes(ContentEntityType::class);
    $definition = $attributes[0]->newInstance();
    $definition->setClass(Aviso::class);
    $definition->setProvider('aculta_messenger_aviso_test');
    $entity_types['aculta_aviso'] = $definition->get();
  }

}
