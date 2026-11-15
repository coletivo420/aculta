<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * The Private Message entity definition.
 *
 * @ContentEntityType(
 *   id = "private_message",
 *   label = @Translation("Private Message"),
 *   handlers = {
 *     "view_builder" = "Drupal\aculta_messenger\Entity\Builder\PrivateMessageViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\aculta_messenger\Form\PrivateMessageForm",
 *       "delete" = "Drupal\aculta_messenger\Form\PrivateMessageDeleteForm",
 *     },
 *     "access" = "Drupal\aculta_messenger\Entity\Access\PrivateMessageAccessControlHandler",
 *   },
 *   base_table = "private_messages",
 *   admin_permission = "administer private messages",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "owner",
 *   },
 *   links = {
 *     "canonical" = "/private-message/{private_message}",
 *     "delete-form" = "/private-message/{private_message}/delete",
 *   },
 *   field_ui_base_route = "aculta_messenger.pm_settings_page",
 * )
 */
class PrivateMessage extends ContentEntityBase implements PrivateMessageInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    /** @var int|string|null $created */
    $created = $this->get('created')->value;
    return (int) $created;
  }

  /**
   * {@inheritdoc}
   *
   * @return mixed
   */
  public function getMessage() {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    /** @var array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields */
    $fields = parent::baseFieldDefinitions($entity_type);
    /** @var array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields */
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    /** @var \Drupal\Core\Field\BaseFieldDefinition $id_field */
    $id_field = $fields['id'];
    $id_field->setLabel(t('Private message ID'))
      ->setDescription(t('The private message ID.'));

    /** @var \Drupal\Core\Field\BaseFieldDefinition $uuid_field */
    $uuid_field = $fields['uuid'];
    $uuid_field->setDescription(t('The custom private message UUID.'));

    // No form field is provided, as the user will always be the current user.
    /** @var \Drupal\Core\Field\BaseFieldDefinition $owner_field */
    $owner_field = $fields['owner'];
    $owner_field
      ->setLabel(t('From'))
      ->setDescription(t('The author of the private message'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Body of the private message.
    $fields['message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Message'))
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'label' => 'hidden',
        'settings' => [
          'placeholder' => 'Message',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_textfield',
        'settings' => [
          'trim_length' => '200',
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the private message was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    /** @var array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields */
    return $fields;
  }

}
