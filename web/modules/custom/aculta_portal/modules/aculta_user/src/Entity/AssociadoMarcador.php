<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Marcador de associado (decisão de 2026-10-16).
 *
 * Marcador puro: não é papel e não concede acesso a nada no site. Fica em tabela própria, separada da identidade
 * User e dos papéis. Serve a relatórios e páginas da associação.
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
    $fields['concedido_por'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Marcado por (uid)'))
      ->setRequired(TRUE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Marcado em'));
    return $fields;
  }

}
