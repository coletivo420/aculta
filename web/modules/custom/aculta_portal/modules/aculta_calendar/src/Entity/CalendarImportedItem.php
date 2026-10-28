<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Revisável, normalizado e não publicado por padrão, item importado de calendário.
 */
#[ContentEntityType(
  id: 'aculta_calendar_imported_item',
  label: new TranslatableMarkup('Item importado de calendário'),
  label_collection: new TranslatableMarkup('Itens importados de calendário'),
  label_singular: new TranslatableMarkup('item importado'),
  label_plural: new TranslatableMarkup('itens importados'),
  base_table: 'aculta_calendar_imported_item',
  revision_table: 'aculta_calendar_imported_item_revision',
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
  admin_permission: 'administer aculta calendar imports',
  common_reference_target: TRUE,
  show_revision_ui: FALSE,
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_calendar_imported_item_list'],
)]
final class CalendarImportedItem extends EditorialContentEntityBase {

  /**
   * Import lifecycle, independent from Drupal's published revision flag.
   *
   * @var array<string, string>
   */
  public const IMPORT_STATES = [
    'rascunho' => 'Rascunho',
    'publicado' => 'Publicado',
    'cancelado' => 'Cancelado',
  ];

  /**
   * Whether the external source still contains this UID.
   *
   * @var array<string, string>
   */
  public const SOURCE_PRESENCE = [
    'presente' => 'Presente na fonte',
    'ausente' => 'Ausente da fonte',
  ];

  /** {@inheritdoc} */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Fonte'))
      ->setSetting('target_type', 'aculta_calendar_import_source')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['external_uid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('UID externo'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Descrição'))
      ->setRevisionable(TRUE);

    $fields['location'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Local'))
      ->setSetting('max_length', 255)
      ->setRevisionable(TRUE);

    $fields['start'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Início'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['end'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Fim'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['all_day'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Dia inteiro'))
      ->setDefaultValue(FALSE)
      ->setRevisionable(TRUE);

    $fields['timezone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Fuso horário'))
      ->setSetting('max_length', 64)
      ->setDefaultValue('America/Sao_Paulo')
      ->setRevisionable(TRUE);

    $fields['timezone_assumed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Fuso horário assumido'))
      ->setDefaultValue(FALSE)
      ->setRevisionable(TRUE);

    $fields['import_state'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Estado da importação'))
      ->setSetting('max_length', 16)
      ->setDefaultValue('rascunho')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    // Source presence is synchronization metadata, not editorial content.
    // It intentionally remains outside revisions so an absent upstream UID
    // cannot hide a currently published item from the site.
    $fields['source_presence'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Presença na fonte'))
      ->setSetting('max_length', 8)
      ->setDefaultValue('presente');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'))
      ->setRevisionable(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'))
      ->setRevisionable(TRUE);

    return $fields;
  }

  /** Sets the import workflow state and its corresponding Drupal publish flag. */
  public function setImportState(string $state): static {
    if (!isset(self::IMPORT_STATES[$state])) {
      throw new \InvalidArgumentException("Estado de importação inválido: $state.");
    }
    $this->set('import_state', $state);
    if ($state === 'publicado') {
      $this->setPublished();
    }
    else {
      $this->setUnpublished();
    }
    return $this;
  }

}
