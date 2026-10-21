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
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\aculta_links\Access\LinkAccessControlHandler;
use Drupal\views\EntityViewsData;

/**
 * An owner's published or private link-in-bio microsite.
 */
#[ContentEntityType(
  id: 'aculta_link_page',
  label: new TranslatableMarkup('Micropágina de links'),
  label_collection: new TranslatableMarkup('Micropáginas de links'),
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
  base_table: 'aculta_link_page',
  revision_table: 'aculta_link_page_revision',
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
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_link_page_list'],
  links: [
    'canonical' => '/painel-administrativo/links/pagina/{aculta_link_page}',
    'add-form' => '/painel-administrativo/links/pagina/adicionar',
    'edit-form' => '/painel-administrativo/links/pagina/{aculta_link_page}/editar',
    'delete-form' => '/painel-administrativo/links/pagina/{aculta_link_page}/excluir',
    'version-history' => '/painel-administrativo/links/pagina/{aculta_link_page}/revisoes',
    'revision' => '/painel-administrativo/links/pagina/{aculta_link_page}/revisao/{aculta_link_page_revision}/visualizar',
    'revision-revert-form' => '/painel-administrativo/links/pagina/{aculta_link_page}/revisao/{aculta_link_page_revision}/reverter',
    'revision-delete-form' => '/painel-administrativo/links/pagina/{aculta_link_page}/revisao/{aculta_link_page_revision}/excluir',
  ],
)]
final class LinkPage extends EditorialContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título público'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Endereço da micropágina'))
      ->setSetting('max_length', 160)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->addConstraint('UniqueField')
      ->addPropertyConstraints('value', [
        'Regex' => [
          'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
          'message' => new TranslatableMarkup('Use letras minúsculas sem acentos, números e hífens entre palavras.'),
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Apresentação'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['theme_preset'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Aparência'))
      ->setSetting('allowed_values', [
        'classic' => 'Clássica',
        'soft' => 'Suave',
        'outline' => 'Contorno',
      ])
      ->setDefaultValue('classic')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['show_owner_identity'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Apresentar identidade pública do perfil'))
      ->setDescription(new TranslatableMarkup('Mostra o apelido público do proprietário quando permitido pelas configurações de privacidade do perfil.'))
      ->setDefaultValue(FALSE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 5,
      ])->setDisplayConfigurable('form', TRUE);
    $fields['uid']->setRevisionable(TRUE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criada em'))
      ->setRevisionable(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterada em'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
