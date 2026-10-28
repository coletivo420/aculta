<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\EntityViewsData;

/**
 * Local de evento, com endereço, coordenadas e referência OSM opcional.
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
    'views_data' => EntityViewsData::class,
  ],
)]
final class EventLocation extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    // Geocodifica quando o endereço muda ou ainda não há coordenadas.
    // Cada endereço é geocodificado uma vez.
    $mudouEndereco = !$this->isNew() && $this->original !== NULL
      && $this->original->get('field_endereco')->getValue() !== $this->get('field_endereco')->getValue();
    // Geocode once when a location is created or its address changes.
    $precisaGeocodificar = $mudouEndereco || ($this->isNew() && $this->get('field_coordenadas')->isEmpty());
    if (\Drupal::hasService('aculta_maps.geocoding') && $precisaGeocodificar) {
      $texto = $this->enderecoTexto();
      $coordenadas = $texto !== '' ? \Drupal::service('aculta_maps.geocoding')->geocode($texto) : NULL;
      if ($coordenadas !== NULL) {
        $this->set('field_coordenadas', [
          'lat' => $coordenadas['lat'],
          'lon' => $coordenadas['lng'],
          'value' => sprintf('POINT (%F %F)', $coordenadas['lng'], $coordenadas['lat']),
          'geo_type' => 'Point',
        ]);
      }
      elseif ($mudouEndereco) {
        $this->set('field_coordenadas', NULL);
      }
    }
  }

  /**
   * Endereço em texto para a geocodificação (sem CEP genérico quando há rua).
   */
  private function enderecoTexto(): string {
    $item = $this->get('field_endereco')->first();
    if ($item === NULL) {
      return '';
    }
    $e = $item->getValue();
    return trim(implode(', ', array_filter([
      (string) ($e['address_line1'] ?? ''),
      (string) ($e['locality'] ?? ''),
      (string) ($e['administrative_area'] ?? ''),
      (string) ($e['postal_code'] ?? ''),
      'Brasil',
    ])));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome do local'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    // Exato ou aproximado. Campo próprio, sem origem no OpenCulturas.
    // Ver auditoria T9.
    $fields['field_precisao'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Precisão do endereço'))
      ->setDefaultValue('exato')
      ->setSetting('allowed_values', [
        'exato' => new TranslatableMarkup('Exato'),
        'aproximado' => new TranslatableMarkup('Aproximado (só bairro e município)'),
      ]);

    // Tipo de local: termo do vocabulário aculta_location_type.
    // O vocabulário é portado do OpenCulturas.
    $fields['field_tipo'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tipo de local'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['aculta_location_type' => 'aculta_location_type']]);

    // Endereço postal (módulo address). Só Brasil no ACULTA.
    $fields['field_endereco'] = BaseFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Endereço'))
      ->setSetting('available_countries', ['BR']);

    // Coordenadas para o mapa (geofield).
    // O endereço é geocodificado no servidor (ADR-014).
    $fields['field_coordenadas'] = BaseFieldDefinition::create('geofield')
      ->setLabel(new TranslatableMarkup('Coordenadas'));

    $fields['field_horario'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Horário de funcionamento'));

    $fields['field_osm_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador do OpenStreetMap'))
      ->setSetting('max_length', 64);

    $fields['field_osm_version'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Versão do elemento do OpenStreetMap'))
      ->setSetting('max_length', 32);

    return $fields;
  }

}
