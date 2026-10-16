<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Conquista concedida a uma pessoa (plano aculta_user, seção G).
 *
 * Fato histórico: a linha não é apagada ao revogar. Revogação grava quem revogou e quando.
 */
#[ContentEntityType(
  id: 'aculta_badge_award',
  label: new TranslatableMarkup('Conquista concedida'),
  label_collection: new TranslatableMarkup('Conquistas concedidas'),
  base_table: 'aculta_badge_award',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid'],
)]
final class BadgeAward extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Pessoa'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['badge'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Conquista'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);
    $fields['origem'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Origem'))
      ->setSetting('max_length', 16)
      ->setRequired(TRUE);
    $fields['concedido_em'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Obtida em'))
      ->setRequired(TRUE);
    $fields['concedido_por'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Concedida por'))
      ->setSetting('target_type', 'user');
    $fields['revogado_em'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Revogada em'));
    $fields['revogado_por'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Revogada por'))
      ->setSetting('target_type', 'user');
    $fields['motivo_concessao'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Motivo da concessão administrativa'));
    $fields['motivo_revogacao'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Motivo da revogação administrativa'));
    return $fields;
  }

}
