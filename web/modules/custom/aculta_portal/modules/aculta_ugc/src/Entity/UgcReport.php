<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Denúncia transversal de conteúdo gerado por usuários (ARQ-06).
 *
 * Armazena a denúncia com proteção de privacidade: o UID do denunciante
 * nunca é exposto em notificações ou relatórios públicos.
 */
#[ContentEntityType(
  id: 'aculta_ugc_report',
  label: new TranslatableMarkup('Denúncia UGC'),
  handlers: [
    'access' => UgcReportAccessControlHandler::class,
  ],
  base_table: 'aculta_ugc_report',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'moderar denuncias ugc',
  internal: TRUE,
)]
final class UgcReport extends ContentEntityBase {

  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['target_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tipo da entidade denunciada'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['target_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID da entidade denunciada'))
      ->setRequired(TRUE);

    // Identidade protegida: nunca incluída em avisos.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Denunciante'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['motivo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Motivo'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['purpose'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Purpose da denúncia'))
      ->setSetting('max_length', 32)
      ->setDefaultValue('portal')
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Situação da denúncia'))
      ->setSetting('max_length', 16)
      ->setDefaultValue('pendente')
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