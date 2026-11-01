<?php

declare(strict_types=1);

namespace Drupal\aculta_index_forum_access_test\Hook;

use Drupal\aculta_user\Entity\AssociadoMarcador;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers the production associate marker entity for this Kernel test. */
final class AssociadoEntityTestHooks {

  /**
 * Adds the production marker entity definition without enabling aculta_user. */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(AssociadoMarcador::class))->getAttributes(ContentEntityType::class);
    $definition = $attributes[0]->newInstance();
    $definition->setClass(AssociadoMarcador::class);
    $definition->setProvider('aculta_index_forum_access_test');
    $entity_types['aculta_associado'] = $definition->get();
  }

}
