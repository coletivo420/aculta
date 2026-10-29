<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
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
    'access' => 'Drupal\aculta_events\Access\EventAccessControlHandler',
  ],
)]
final class Event extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    // Slug nasce com o evento e não muda depois (estável após publicado).
    if ($this->get('field_slug')->isEmpty()) {
      $gerador = \Drupal::service('aculta_events.slug');
      $this->set('field_slug', $gerador->uniqueFor((string) $this->label(), $this->id() ? (int) $this->id() : NULL));
    }
  }

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

    // Slug público, gerado na criação (EventSlugGenerator). Não muda depois de
    // publicado.
    $fields['field_slug'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Endereço (slug)'))
      ->setSetting('max_length', 255);

    // Tipo de evento: termo do vocabulário aculta_event_type (migrado em
    // aculta_events_update_10001).
    $fields['field_tipo'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tipo de evento'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['aculta_event_type' => 'aculta_event_type']]);

    $fields['field_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Site do evento'));

    $fields['field_organizador'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organizador'))
      ->setSetting('target_type', 'user');

    // Status do evento: rascunho não aparece no sitemap; cancelado mantém a
    // página com aviso.
    $fields['field_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Situação'))
      ->setDefaultValue('rascunho')
      ->setSetting('allowed_values', [
        'rascunho' => new TranslatableMarkup('Rascunho'),
        'publicado' => new TranslatableMarkup('Publicado'),
        'cancelado' => new TranslatableMarkup('Cancelado'),
      ]);

    // Dados estruturados aditivos (ARQ-04 / OpenCulturas 3.1.5).
    $fields['field_classificacao_etaria'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Classificação etária'))
      ->setDefaultValue('livre')
      ->setSetting('allowed_values', [
        'livre' => new TranslatableMarkup('Livre para todos os públicos'),
        '10' => new TranslatableMarkup('Não recomendado para menores de 10 anos'),
        '12' => new TranslatableMarkup('Não recomendado para menores de 12 anos'),
        '14' => new TranslatableMarkup('Não recomendado para menores de 14 anos'),
        '16' => new TranslatableMarkup('Não recomendado para menores de 16 anos'),
        '18' => new TranslatableMarkup('Não recomendado para menores de 18 anos'),
      ]);

    $fields['field_acessibilidade'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Recursos de acessibilidade'));

    $fields['field_seguranca'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Informações de segurança e orientações'));

    return $fields;
  }

}
