<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Entity\Form\RevisionRevertForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityDeleteForm;
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
  constraints: [
    'AcultaLinkDestinationChoice' => [],
  ],
  show_revision_ui: TRUE,
  common_reference_target: TRUE,
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_link_list'],
  links: [
    'canonical' => '/painel-administrativo/links/destino/{aculta_link}',
    'add-form' => '/painel-administrativo/links/destino/adicionar',
    'edit-form' => '/painel-administrativo/links/destino/{aculta_link}/editar',
    'delete-form' => '/painel-administrativo/links/destino/{aculta_link}/excluir',
    'version-history' => '/painel-administrativo/links/destino/{aculta_link}/revisoes',
    'revision' => '/painel-administrativo/links/destino/{aculta_link}/revisao/{aculta_link_revision}/visualizar',
    'revision-revert-form' => '/painel-administrativo/links/destino/{aculta_link}/revisao/{aculta_link_revision}/reverter',
    'revision-delete-form' => '/painel-administrativo/links/destino/{aculta_link}/revisao/{aculta_link_revision}/excluir',
  ],
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
      ->setSetting('link_type', LinkItemInterface::LINK_GENERIC);
    $fields['uri']->getItemDefinition()->addConstraint('AcultaLinkDestination');
    $fields['uri']
      ->setSetting('title', DRUPAL_DISABLED)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['target_node'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Conteúdo do ACULTA'))
      ->setDescription(new TranslatableMarkup('Opcional: escolha conteúdo Drupal publicado em vez de informar uma URL.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['short_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Código do link curto'))
      ->setDescription(new TranslatableMarkup('Código público permanente gerado automaticamente para destinos publicados.'))
      ->setSetting('max_length', 12)
      ->addConstraint('UniqueField')
      ->addPropertyConstraints('value', [
        'Regex' => [
          'pattern' => '/^[a-z0-9]{12}$/D',
          'message' => new TranslatableMarkup('O código deve conter 12 letras minúsculas ou números.'),
        ],
      ]);

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
