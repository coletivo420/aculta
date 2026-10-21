<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Entity\Form\RevisionRevertForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
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
    'form' => [
      'default' => EntityForm::class,
      'add' => EntityForm::class,
      'edit' => EntityForm::class,
      'delete' => EntityDeleteForm::class,
      'revision-revert' => RevisionRevertForm::class,
      'revision-delete' => RevisionDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
      'revision' => RevisionHtmlRouteProvider::class,
    ],
  ],
  base_table: 'aculta_link_item',
  revision_table: 'aculta_link_item_revision',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'uuid' => 'uuid',
    'label' => 'title',
    'published' => 'status',
    'owner' => 'uid',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
  admin_permission: 'administer aculta links',
  constraints: [
    'AcultaLinkItemSchedule' => [],
    'AcultaLinkItemOwnership' => [],
  ],
  common_reference_target: TRUE,
  show_revision_ui: TRUE,
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_link_item_list'],
  links: [
    'canonical' => '/painel-administrativo/links/item/{aculta_link_item}',
    'add-form' => '/painel-administrativo/links/item/adicionar',
    'edit-form' => '/painel-administrativo/links/item/{aculta_link_item}/editar',
    'delete-form' => '/painel-administrativo/links/item/{aculta_link_item}/excluir',
    'version-history' => '/painel-administrativo/links/item/{aculta_link_item}/revisoes',
    'revision' => '/painel-administrativo/links/item/{aculta_link_item}/revisao/{aculta_link_item_revision}/visualizar',
    'revision-revert-form' => '/painel-administrativo/links/item/{aculta_link_item}/revisao/{aculta_link_item_revision}/reverter',
    'revision-delete-form' => '/painel-administrativo/links/item/{aculta_link_item}/revisao/{aculta_link_item_revision}/excluir',
  ],
)]
final class LinkItem extends EditorialContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']->setRevisionable(TRUE);

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

    $fields['available_from'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Disponível a partir de'))
      ->setDescription(new TranslatableMarkup('Horário no fuso padrão do portal; vazio deixa o item disponível imediatamente.'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATETIME)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['available_until'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Disponível até'))
      ->setDescription(new TranslatableMarkup('Horário no fuso padrão do portal; vazio mantém o item disponível sem prazo final.'))
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATETIME)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE);

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
      'weight' => 5,
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
