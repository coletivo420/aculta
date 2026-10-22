<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * A revisionable post or reply in an ACULTA public Forum topic.
 *
 * This is a single-bundle entity. Returning the entity type ID as its bundle
 * lets Core Workflows associate a moderation workflow without a mutable bundle
 * configuration entity.
 */
#[ContentEntityType(
  id: 'aculta_forum_post',
  label: new TranslatableMarkup('Publicação do Fórum'),
  label_collection: new TranslatableMarkup('Publicações do Fórum'),
  handlers: [
    'access' => '\\Drupal\\Core\\Entity\\EntityAccessControlHandler',
    'form' => [
      'default' => ContentEntityForm::class,
      'public_submission' => '\\Drupal\\aculta_forum\\Form\\ForumPostSubmissionForm',
    ],
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'aculta_forum_post',
  data_table: 'aculta_forum_post_field_data',
  revision_table: 'aculta_forum_post_revision',
  revision_data_table: 'aculta_forum_post_field_revision',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
    'label' => 'subject',
    'owner' => 'uid',
    'published' => 'status',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
  admin_permission: 'administer aculta forum posts',
  collection_permission: 'administer aculta forum posts',
  show_revision_ui: FALSE,
  translatable: TRUE,
  common_reference_target: TRUE,
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_forum_post_list'],
)]
final class ForumPost extends EditorialContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function bundle(): string {
    return 'aculta_forum_post';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['uid']->setRevisionable(TRUE);
    $fields['topic'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tópico'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', ['target_bundles' => ['aculta_forum_topic' => 'aculta_forum_topic']])
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);
    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Publicação respondida'))
      ->setSetting('target_type', 'aculta_forum_post')
      ->setRevisionable(TRUE);
    $fields['thread_position'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Posição na conversa'))
      ->setDefaultValue(0)
      ->setRevisionable(TRUE);
    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Assunto'))
      ->setSetting('max_length', 255)
      ->setRevisionable(TRUE);
    $fields['subject']
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);
    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Texto'))
      ->setSetting('allowed_formats', ['forum'])
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['status']->setDefaultValue(FALSE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criada em'))
      ->setRevisionable(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterada em'))
      ->setRevisionable(TRUE);
    $fields['first_published'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Primeira publicação'))
      ->setDescription(new TranslatableMarkup('Timestamp imutável para calcular a janela de edição do autor.'));

    return $fields;
  }

}
