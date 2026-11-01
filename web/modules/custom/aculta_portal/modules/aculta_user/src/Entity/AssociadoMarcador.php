<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Situação de associado (decisão do responsável, 2026-10-21).
 *
 * Associado é uma situação, não um papel: não concede acesso a nada no site por si. Cada conta tem no máximo uma linha
 * com a situação atual (pendente, ativo, suspenso ou desligado), a data de adesão (primeira ativação) e quem alterou
 * por último, com o motivo. Pedido de adesão cria a linha como pendente; só o administrador concede, suspende ou
 * desliga. Fica em tabela própria, separada da identidade User e dos papéis.
 */
#[ContentEntityType(
  id: 'aculta_associado',
  label: new TranslatableMarkup('Marcador de associado'),
  handlers: [
    'access' => AssociadoMarcadorAccessControlHandler::class,
  ],
  base_table: 'aculta_associado',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'administer aculta associados',
  internal: TRUE,
)]
final class AssociadoMarcador extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Conta'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['situacao'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Situação'))
      ->setSetting('max_length', 16)
      ->setRequired(TRUE)
      ->setDefaultValue('pendente');
    $fields['data_adesao'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Data de adesão (primeira ativação)'));
    $fields['concedido_por'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Última alteração por (uid)'));
    $fields['motivo'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Motivo da última alteração'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Pedido ou marca criado em'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));
    return $fields;
  }

}
