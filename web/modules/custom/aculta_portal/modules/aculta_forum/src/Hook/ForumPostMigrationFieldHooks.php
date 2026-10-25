<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds migration metadata fields to the revisionable public Forum post.
 */
final class ForumPostMigrationFieldHooks {

  /**
   * Declares fields needed to preserve historical Forum comment data.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Base fields keyed by field name.
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'aculta_forum_post') {
      return [];
    }

    return [
      'legacy_author' => BaseFieldDefinition::create('entity_reference')
        ->setLabel(new TranslatableMarkup('Autoria histórica'))
        ->setDescription(new TranslatableMarkup('Referência imutável ao autor histórico importado do Fórum.'))
        ->setSetting('target_type', 'aculta_legado_autor')
        ->setCardinality(1)
        ->setRevisionable(TRUE)
        ->setReadOnly(TRUE),
    ];
  }

}
