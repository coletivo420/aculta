<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Evento: descrição reutilizável, com uma ou mais datas (MODELO-DE-DADOS.md).
 *
 * Campos de tipo e categoria (vocabulários) ficam para a fase de catálogo (F3).
 */
#[ContentEntityType(
  id: 'aculta_event',
  label: new TranslatableMarkup('Evento'),
  label_collection: new TranslatableMarkup('Eventos'),
  label_singular: new TranslatableMarkup('evento'),
  label_plural: new TranslatableMarkup('eventos'),
  base_table: 'aculta_event',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
  ],
  handlers: [
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
)]
final class Event extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['field_subtitulo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Subtítulo'))
      ->setSetting('max_length', 255);

    $fields['body'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Descrição'));

    $fields['field_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Site do evento'));

    $fields['field_organizador'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organizador'))
      ->setSetting('target_type', 'user');

    // Status do evento: rascunho não aparece no sitemap; cancelado mantém a página com aviso.
    $fields['field_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Situação'))
      ->setDefaultValue('rascunho')
      ->setSetting('allowed_values', [
        'rascunho' => new TranslatableMarkup('Rascunho'),
        'publicado' => new TranslatableMarkup('Publicado'),
        'cancelado' => new TranslatableMarkup('Cancelado'),
      ]);

    return $fields;
  }

}
