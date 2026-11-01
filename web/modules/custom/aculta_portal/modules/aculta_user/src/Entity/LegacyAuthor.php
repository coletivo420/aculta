<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_user\Entity\LegacyAuthorAccessControlHandler;

/**
 * Autoria histórica do Fórum (ACULTA Fórum, F2).
 *
 * Não é usuário do Drupal: não tem conta, não tem login, não aparece em /admin/people nem em listas de usuários.
 * Fica em tabela própria, separada da identidade User. Conteúdo histórico (tópicos e comentários) aponta para
 * esta entidade pelo campo field_autor_legado e tem uid 0.
 *
 * Motivo: a decisão do responsável (2026-10-16) é que autores legados não se misturem aos usuários do site.
 */
#[ContentEntityType(
  id: 'aculta_legado_autor',
  label: new TranslatableMarkup('Autoria histórica do Fórum'),
  handlers: [
    'access' => LegacyAuthorAccessControlHandler::class,
  ],
  base_table: 'aculta_legado_autor',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'nome',
  ],
  admin_permission: 'administer aculta legado',
  internal: TRUE,
)]
final class LegacyAuthor extends ContentEntityBase {

  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Identificador estável da autoria no acervo. Chave da importação: reexecutar não cria duplicata.
    $fields['origem_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador de origem'))
      ->setSetting('max_length', 64)
      ->setSetting('is_ascii', TRUE)
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);
    $fields['nome'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome histórico'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));

    return $fields;
  }

}
