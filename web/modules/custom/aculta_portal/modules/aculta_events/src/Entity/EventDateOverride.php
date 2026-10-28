<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Exceção de uma ocorrência de série: cancelamento ou reagendamento (RECORRENCIA.md, M1).
 *
 * A chave é `field_inicio_original`, o início gerado pela regra. Início e fim vazios
 * significam cancelada; preenchidos, a ocorrência passa para o novo horário.
 */
#[ContentEntityType(
  id: 'aculta_event_override',
  label: new TranslatableMarkup('Exceção de ocorrência'),
  label_collection: new TranslatableMarkup('Exceções de ocorrência'),
  label_singular: new TranslatableMarkup('exceção de ocorrência'),
  label_plural: new TranslatableMarkup('exceções de ocorrência'),
  base_table: 'aculta_event_override',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
)]
final class EventDateOverride extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Data de origem: a série com a mesma data de início é do evento pai.
    $fields['field_data_pai'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Data do evento'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'aculta_event_date');

    $fields['field_inicio_original'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Início original da ocorrência'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime');

    // Vazios = cancelada.
    $fields['field_inicio'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Novo início'))
      ->setSetting('datetime_type', 'datetime');

    $fields['field_fim'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Novo fim'))
      ->setSetting('datetime_type', 'datetime');

    $fields['field_motivo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Motivo'))
      ->setSetting('max_length', 255);

    return $fields;
  }

}
