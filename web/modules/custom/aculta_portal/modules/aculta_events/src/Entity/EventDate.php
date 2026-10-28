<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\LinkItemInterface;
use Drupal\views\EntityViewsData;

/**
 * Data de um evento: intervalo, preço e recorrência (MODELO-DE-DADOS.md).
 *
 * `field_quando` é o primeiro intervalo da série. As ocorrências são calculadas
 * na leitura pelo expansor de `aculta_calendar` (RECORRENCIA.md, M2).
 * Adaptado de OpenCulturas 3.1.4 (GPL-2.0-or-later),
 * `profile/config/install/field.storage.node.field_pricing.yml`.
 * `field_pricing_info` e `field_ticketing` também mapeiam campos da origem;
 * nenhum processamento de pagamento foi trazido.
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
    'views_data' => EntityViewsData::class,
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

    // Evento obrigatório e local físico opcional quando a data é virtual.
    $fields['field_evento'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Evento'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'aculta_event');

    $fields['field_local'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Local'))
      ->setSetting('target_type', 'aculta_event_location');

    // Intervalo com início e fim; dia inteiro usa só a data de `field_quando`.
    $fields['field_quando'] = BaseFieldDefinition::create('daterange')
      ->setLabel(new TranslatableMarkup('Quando'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime');

    $fields['field_dia_inteiro'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Dia inteiro'))
      ->setDefaultValue(FALSE);

    // Informações de preço não processam pagamento dentro do ACULTA (D-29).
    $fields['field_gratuito'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Entrada gratuita'))
      ->setDefaultValue(FALSE);

    $fields['field_pricing'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Preço'))
      ->setSetting('max_length', 255);

    // Texto simples evita permitir HTML livre em informações de preço.
    $fields['field_pricing_info'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Detalhes do preço'));

    $fields['field_ticketing'] = BaseFieldDefinition::create('link')
      ->setLabel(new TranslatableMarkup('Compra ou reserva externa'))
      ->setSetting('link_type', LinkItemInterface::LINK_EXTERNAL);

    // RRULE normalizada pelo RecurrenceRuleParser. Vazio = evento único.
    $fields['field_regra'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Regra de recorrência'))
      ->setSetting('max_length', 255);

    // Horizonte mensal de série aberta (padrão 12, como month_limit na origem).
    $fields['field_limite_meses'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Limite de série aberta (meses)'))
      ->setDefaultValue(12);

    return $fields;
  }

}
