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
 * Vínculo entre conta de usuário e organização (ARQ-05, D-01).
 *
 * Não cria papel global no Drupal. O vínculo define o escopo em que um usuário
 * com a função content_editor pode atuar em nome da organização ou gerenciar
 * seus conteúdos específicos.
 */
#[ContentEntityType(
  id: 'aculta_org_member',
  label: new TranslatableMarkup('Vínculo de membro de organização'),
  handlers: [
    'access' => OrganizationMemberAccessControlHandler::class,
  ],
  base_table: 'aculta_org_member',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'administer aculta claims',
  internal: TRUE,
)]
final class OrganizationMember extends ContentEntityBase {

  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Conta'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['org_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador da Organização'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['role_in_org'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Papel interno na organização'))
      ->setSetting('max_length', 32)
      ->setDefaultValue('member')
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Situação do vínculo'))
      ->setSetting('max_length', 16)
      ->setDefaultValue('pending')
      ->setRequired(TRUE);

    $fields['approved_by'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Aprovado por (UID do moderador)'));

    $fields['approved_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Data de aprovação'));

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Observações / Motivo'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));

    return $fields;
  }

}