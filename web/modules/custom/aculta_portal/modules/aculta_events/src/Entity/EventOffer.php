<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\EntityViewsData;

/**
 * Oferta/lote de ingresso pertencente ao módulo ACULTA Eventos.
 *
 * Esta entidade descreve uma oferta e sua capacidade nominal. Não representa
 * pedido, pagamento, inscrição ou credencial. Não possui rotas públicas e
 * começa inativa; emissão e disponibilidade dependem de fluxos próprios com
 * idempotência, controle de acesso e revisão LGPD.
 */
#[ContentEntityType(
  id: 'aculta_event_offer',
  label: new TranslatableMarkup('Oferta de ingresso'),
  label_collection: new TranslatableMarkup('Ofertas de ingresso'),
  label_singular: new TranslatableMarkup('oferta de ingresso'),
  label_plural: new TranslatableMarkup('ofertas de ingresso'),
  base_table: 'aculta_event_offer',
  admin_permission: 'administer aculta events',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
  ],
  handlers: [
    'storage' => 'Drupal\\Core\\Entity\\Sql\\SqlContentEntityStorage',
    'access' => 'Drupal\\aculta_events\\Access\\EventOfferAccessControlHandler',
    'views_data' => EntityViewsData::class,
  ],
)]
final class EventOffer extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome da oferta'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['field_evento'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Evento'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'aculta_event');

    // NULL significa que a oferta se aplica ao Evento ou à série. Uma Data
    // específica identifica uma ocorrência sem duplicar a entidade EventDate.
    $fields['field_data'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Data específica'))
      ->setSetting('target_type', 'aculta_event_date');

    $fields['field_modalidade'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Modalidade da oferta'))
      ->setRequired(TRUE)
      ->setDefaultValue('gratuita')
      ->setSetting('allowed_values', [
        'gratuita' => new TranslatableMarkup('Gratuita'),
        'paga' => new TranslatableMarkup('Paga'),
      ]);

    // Valor descritivo da oferta paga. Não é saldo, pedido, pagamento ou
    // registro financeiro; a moeda será definida pelo contrato Commerce.
    $fields['field_preco'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Preço unitário'))
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->addPropertyConstraints('value', ['Range' => ['min' => 0]]);

    // Vazio representa capacidade não informada. A disponibilidade efetiva
    // será calculada pelo serviço transacional de inscrições, ainda pendente.
    $fields['field_capacidade'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Capacidade máxima'))
      ->addPropertyConstraints('value', ['Range' => ['min' => 1]]);

    $fields['field_limite_por_pedido'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Limite por inscrição ou pedido'))
      ->setDefaultValue(1)
      ->addPropertyConstraints('value', ['Range' => ['min' => 1]]);

    $fields['field_inicio_vendas'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Início da disponibilidade'))
      ->setSetting('datetime_type', 'datetime');

    $fields['field_fim_vendas'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Fim da disponibilidade'))
      ->setSetting('datetime_type', 'datetime');

    $fields['field_ativa'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Oferta ativa'))
      ->setDefaultValue(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criada em'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterada em'));

    return $fields;
  }

}
