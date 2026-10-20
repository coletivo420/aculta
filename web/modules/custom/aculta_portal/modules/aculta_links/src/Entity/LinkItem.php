<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\EntityViewsData;
use Drupal\aculta_links\Access\LinkItemAccessControlHandler;

/**
 * Ordered presentation of a reusable link inside one microsite.
 */
#[ContentEntityType(
  id: 'aculta_link_item',
  label: new TranslatableMarkup('Item de micropágina'),
  label_collection: new TranslatableMarkup('Itens de micropágina'),
  handlers: [
    'access' => LinkItemAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'aculta_link_item',
  revision_table: 'aculta_link_item_revision',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'uuid' => 'uuid',
    'label' => 'title',
    'published' => 'status',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
  admin_permission: 'administer aculta links',
  common_reference_target: TRUE,
)]
final class LinkItem extends EditorialContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['page'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Micropágina'))
      ->setSetting('target_type', 'aculta_link_page')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['link'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Destino'))
      ->setSetting('target_type', 'aculta_link')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Texto do botão'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Descrição do botão'))
      ->setSetting('max_length', 255)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Posição'))
      ->setDefaultValue(0)
      ->setRevisionable(TRUE);

    $fields['page']->setDisplayOptions('form', [
      'type' => 'entity_reference_autocomplete',
      'weight' => -10,
    ])->setDisplayConfigurable('form', TRUE);
    $fields['link']->setDisplayOptions('form', [
      'type' => 'entity_reference_autocomplete',
      'weight' => -9,
    ])->setDisplayConfigurable('form', TRUE);
    $fields['status']->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'settings' => [
        'display_label' => TRUE,
      ],
      'weight' => 2,
    ])->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'))
      ->setRevisionable(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
