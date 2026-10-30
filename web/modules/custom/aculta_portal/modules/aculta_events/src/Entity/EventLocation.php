<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
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
    'access' => 'Drupal\aculta_events\Access\EventLocationAccessControlHandler',
    'views_data' => EntityViewsData::class,
    'form' => [
      'default' => 'Drupal\aculta_events\Form\EventLocationForm',
      'add' => 'Drupal\aculta_events\Form\EventLocationForm',
      'edit' => 'Drupal\aculta_events\Form\EventLocationForm',
      'delete' => 'Drupal\aculta_events\Form\EventLocationDeleteForm',
    ],
  ],
  links: [
    'canonical' => '/locais/{aculta_event_location}',
    'add-form' => '/painel-administrativo/eventos/locais/adicionar',
    'edit-form' => '/painel-administrativo/eventos/locais/{aculta_event_location}/editar',
    'delete-form' => '/painel-administrativo/eventos/locais/{aculta_event_location}/excluir',
  ],
)]
final class EventLocation extends ContentEntityBase implements EntityOwnerInterface {

  /**
   * {@inheritdoc}
   */
  public function getOwner(): ?UserInterface {
    return $this->get('field_organizador')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): ?int {
    $targetId = $this->get('field_organizador')->target_id;
    return $targetId !== NULL && $targetId !== '' ? (int) $targetId : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): static {
    $this->set('field_organizador', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): static {
    $this->set('field_organizador', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    // Força normalização do país para Brasil em qualquer persistência.
    if ($this->hasField('field_endereco') && !$this->get('field_endereco')->isEmpty()) {
      foreach ($this->get('field_endereco') as $item) {
        if ($item->country_code !== 'BR') {
          $item->country_code = 'BR';
        }
      }
    }
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
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []): Url {
    if ($rel === 'canonical') {
      return Url::fromRoute('aculta_events.location_canonical', [
        'aculta_event_location' => $this->id(),
      ], $options);
    }
    if ($rel === 'edit-form') {
      return Url::fromRoute('aculta_events.location_edit', [
        'aculta_event_location' => $this->id(),
      ], $options);
    }
    if ($rel === 'delete-form') {
      return Url::fromRoute('aculta_events.location_delete', [
        'aculta_event_location' => $this->id(),
      ], $options);
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * Retorna a URL canônica absoluta no purpose/domínio de eventos.
   */
  public function toCanonicalUrl(?DomainPurposeManager $purposes = NULL): Url {
    if ($purposes !== NULL && $this->id() !== NULL) {
      $canonical = $purposes->canonicalRouteUrl('eventos', 'aculta_events.location_canonical', [
        'aculta_event_location' => (string) $this->id(),
      ]);
      if ($canonical !== NULL) {
        return $canonical;
      }
    }
    return $this->toUrl('canonical');
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
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE);

    // Exato ou aproximado. Campo próprio, sem origem no OpenCulturas.
    // Ver auditoria T9.
    $fields['field_precisao'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Precisão do endereço'))
      ->setDefaultValue('exato')
      ->setSetting('allowed_values', [
        'exato' => new TranslatableMarkup('Exato'),
        'aproximado' => new TranslatableMarkup('Aproximado (só bairro e município)'),
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 25])
      ->setDisplayConfigurable('form', TRUE);

    // Tipo de local: termo do vocabulário aculta_location_type.
    // O vocabulário é portado do OpenCulturas.
    $fields['field_tipo'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tipo de local'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['aculta_location_type' => 'aculta_location_type']])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE);

    // Endereço postal (módulo address). Só Brasil no ACULTA.
    $fields['field_endereco'] = BaseFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Endereço'))
      ->setSetting('available_countries', ['BR'])
      ->setSetting('field_overrides', [
        'givenName' => ['override' => 'hidden'],
        'familyName' => ['override' => 'hidden'],
        'additionalName' => ['override' => 'hidden'],
        'organization' => ['override' => 'hidden'],
        'addressLine3' => ['override' => 'hidden'],
      ])
      ->setDisplayOptions('form', ['type' => 'address_default', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE);

    // Coordenadas para o mapa (geofield).
    // O endereço é geocodificado no servidor (ADR-014).
    $fields['field_coordenadas'] = BaseFieldDefinition::create('geofield')
      ->setLabel(new TranslatableMarkup('Coordenadas'));

    $fields['field_horario'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Horário de funcionamento'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_osm_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador do OpenStreetMap'))
      ->setSetting('max_length', 64);

    $fields['field_osm_version'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Versão do elemento do OpenStreetMap'))
      ->setSetting('max_length', 32);

    $fields['field_allow_claiming'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Permitir reivindicação de posse'))
      ->setDescription(new TranslatableMarkup('Permite que outros usuários solicitem a titularidade deste local à moderação.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 45])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_organizador'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organizador / Responsável'))
      ->setSetting('target_type', 'user');

    $fields['field_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Situação'))
      ->setDefaultValue('publicado')
      ->setSetting('allowed_values', [
        'rascunho' => new TranslatableMarkup('Rascunho'),
        'publicado' => new TranslatableMarkup('Publicado'),
        'privado' => new TranslatableMarkup('Privado'),
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_descricao'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Descrição do local'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 15])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_telefone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Telefone de contato'))
      ->setSetting('max_length', 32)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 35])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_site'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Site ou página do local'))
      ->setDisplayOptions('form', ['type' => 'uri', 'weight' => 40])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
