<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_last_activity_test\Hook;

use Drupal\aculta_forum\Hook\ForumPostMigrationFieldHooks;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Supplies production migration base fields to the isolated Kernel fixture.
 */
final class ForumLastActivityTestHooks {

  /**
   * Delegates the historical-author field definition to the production hook.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Base fields keyed by field name.
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    return (new ForumPostMigrationFieldHooks())->entityBaseFieldInfo($entity_type);
  }

}
