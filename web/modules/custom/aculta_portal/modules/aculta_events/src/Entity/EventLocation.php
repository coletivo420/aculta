<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Local de evento. Endereço e coordenadas (address e geofield) ficam para a F4.
 */
#[ContentEntityType(
  id: 'aculta_event_location',
  label: new TranslatableMarkup('Local de evento'),
  label_collection: new TranslatableMarkup('Locais de evento'),
  label_singular: new TranslatableMarkup('local de evento'),
  label_plural: new TranslatableMarkup('locais de evento'),
  base_table: 'aculta_event_location',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
  ],
  handlers: [
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
)]
final class EventLocation extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome do local'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    // Exato ou aproximado. Campo próprio, sem origem no OpenCulturas (auditoria, T9).
    $fields['field_precisao'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Precisão do endereço'))
      ->setDefaultValue('exato')
      ->setSetting('allowed_values', [
        'exato' => new TranslatableMarkup('Exato'),
        'aproximado' => new TranslatableMarkup('Aproximado (só bairro e município)'),
      ]);

    // Tipo de local: termo do vocabulário aculta_location_type (portado do OpenCulturas).
    $fields['field_tipo'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tipo de local'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['aculta_location_type' => 'aculta_location_type']]);

    // Endereço postal (módulo address). Só Brasil no ACULTA.
    $fields['field_endereco'] = BaseFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Endereço'))
      ->setSetting('available_countries', ['BR']);

    // Coordenadas para o mapa (geofield). O endereço é geocodificado no servidor (ADR-014).
    $fields['field_coordenadas'] = BaseFieldDefinition::create('geofield')
      ->setLabel(new TranslatableMarkup('Coordenadas'));

    $fields['field_horario'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Horário de funcionamento'));

    return $fields;
  }

}
