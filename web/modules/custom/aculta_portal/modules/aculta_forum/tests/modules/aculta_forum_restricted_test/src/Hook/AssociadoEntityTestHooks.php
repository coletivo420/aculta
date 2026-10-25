<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_restricted_test\Hook;

use Drupal\aculta_user\Entity\AssociadoMarcador;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers the real AssociadoMarcador definition without enabling aculta_user.
 */
final class AssociadoEntityTestHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(AssociadoMarcador::class))->getAttributes(ContentEntityType::class);
    $definition = $attributes[0]->newInstance();
    $definition->setClass(AssociadoMarcador::class);
    $definition->setProvider('aculta_forum_restricted_test');
    $entity_types['aculta_associado'] = $definition->get();
  }

}
