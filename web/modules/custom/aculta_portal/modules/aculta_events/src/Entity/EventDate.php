<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Data de um evento: intervalo, dia inteiro e regra de recorrência (MODELO-DE-DADOS.md).
 *
 * `field_quando` é o primeiro intervalo da série. As demais ocorrências são calculadas
 * na leitura pelo expansor de `aculta_calendar` (RECORRENCIA.md, M2).
 */
#[ContentEntityType(
  id: 'aculta_event_date',
  label: new TranslatableMarkup('Data de evento'),
  label_collection: new TranslatableMarkup('Datas de evento'),
  label_singular: new TranslatableMarkup('data de evento'),
  label_plural: new TranslatableMarkup('datas de evento'),
  base_table: 'aculta_event_date',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
  ],
  handlers: [
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
)]
final class EventDate extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    // Intervalo com início e fim. Para dia inteiro, `field_dia_inteiro` manda usar só a data.
    $fields['field_quando'] = BaseFieldDefinition::create('daterange')
      ->setLabel(new TranslatableMarkup('Quando'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime');

    $fields['field_dia_inteiro'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Dia inteiro'))
      ->setDefaultValue(FALSE);

    // RRULE normalizada pelo RecurrenceRuleParser. Vazio = evento único.
    $fields['field_regra'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Regra de recorrência'))
      ->setSetting('max_length', 255);

    // Horizonte de série aberta, em meses, a partir de agora (padrão 12, como month_limit no OpenCulturas).
    $fields['field_limite_meses'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Limite de série aberta (meses)'))
      ->setDefaultValue(12);

    return $fields;
  }

}
