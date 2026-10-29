<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Pedido de reivindicação de posse ou titularidade (ARQ-05, D-02).
 *
 * Padrão fechado: o pedido vai para moderação e não altera dados nem
 * permissões até a aprovação formal por moderador autorizado.
 */
#[ContentEntityType(
  id: 'aculta_ownership_claim',
  label: new TranslatableMarkup('Pedido de reivindicação de posse'),
  handlers: [
    'access' => OwnershipClaimAccessControlHandler::class,
  ],
  base_table: 'aculta_ownership_claim',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'administer aculta claims',
  internal: TRUE,
)]
final class OwnershipClaim extends ContentEntityBase {

  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['target_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tipo da entidade reivindicada'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['target_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID da entidade reivindicada'))
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Solicitante'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['justificativa'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Justificativa / Comprovação de vínculo'))
      ->setRequired(TRUE);

    $fields['contato'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contato ou documento (opcional)'))
      ->setSetting('max_length', 255);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Situação do pedido'))
      ->setSetting('max_length', 16)
      ->setDefaultValue('pending')
      ->setRequired(TRUE);

    $fields['decided_by'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Decidido por (UID do moderador)'));

    $fields['decided_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Data da decisão'));

    $fields['decision_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Parecer da moderação'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));

    return $fields;
  }

}