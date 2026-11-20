<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Autoria histórica do fórum legado; não representa uma conta Drupal.
 *
 * Não tem login nem aparece em /admin/people ou listas de usuários. Tópicos e
 * Comments importados podem apontar para esta entidade por field_autor_legado;
 * a importação ainda não foi executada no Runtime alvo.
 *
 * F-D44 impede que autoria histórica se misture à identidade User.
 */
#[ContentEntityType(
  id: 'aculta_forum_legacy_user',
  label: new TranslatableMarkup('Autoria histórica do Fórum'),
  handlers: [
    'access' => LegacyForumUserAccessControlHandler::class,
  ],
  base_table: 'aculta_forum_legacy_user',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'nome',
  ],
  admin_permission: 'administer aculta legado',
  internal: TRUE,
)]
final class LegacyForumUser extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Define os campos base da autoria histórica.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Definição do tipo de entidade.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Campos base indexados pelo nome.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Identificador estável da autoria no acervo. Reexecuções não duplicam.
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
