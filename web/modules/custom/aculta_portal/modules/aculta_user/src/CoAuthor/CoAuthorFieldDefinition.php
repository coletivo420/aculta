<?php

declare(strict_types=1);

namespace Drupal\aculta_user\CoAuthor;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * ACULTA-owned node field blueprint, not registered before the cutover.
 *
 * The eventual entity_base_field_info hook must call this factory for node
 * entities only. No storage is installed by this class alone.
 */
final class CoAuthorFieldDefinition {

  /**
   * Creates the revisionable, multivalued user-reference field.
   */
  public static function build(EntityTypeInterface $entityType): BaseFieldDefinition {
    if ($entityType->id() !== 'node') {
      throw new \InvalidArgumentException('Co-author field is only available for node entities.');
    }

    return BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Coautores'))
      ->setDescription(new TranslatableMarkup('Pessoas com coautoria deste conteúdo. A atribuição não concede permissões por si só.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'user')
      ->setRevisionable(TRUE)
      ->setTranslatable($entityType->isTranslatable())
      ->setDisplayOptions('view', ['region' => 'hidden'])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => 17,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);
  }

}
