<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\aculta_links\Access\LinkAccessControlHandler;
use Drupal\views\EntityViewsData;

/**
 * A reusable, owner-managed destination for an ACULTA Links page.
 */
#[ContentEntityType(
  id: 'aculta_link',
  label: new TranslatableMarkup('Link'),
  label_collection: new TranslatableMarkup('Links'),
  handlers: [
    'access' => LinkAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'aculta_link',
  revision_table: 'aculta_link_revision',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'uuid' => 'uuid',
    'label' => 'title',
    'owner' => 'uid',
    'published' => 'status',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
  admin_permission: 'administer aculta links',
  collection_permission: 'access aculta links overview',
  show_revision_ui: TRUE,
  common_reference_target: TRUE,
)]
final class Link extends EditorialContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uri'] = BaseFieldDefinition::create('link')
      ->setLabel(new TranslatableMarkup('Destino'))
      ->setRequired(TRUE)
      ->setSetting('link_type', LinkItemInterface::LINK_GENERIC)
      ->setSetting('title', DRUPAL_DISABLED)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']->setRevisionable(TRUE);
    $fields['status']->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'settings' => [
        'display_label' => TRUE,
      ],
      'weight' => 20,
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
