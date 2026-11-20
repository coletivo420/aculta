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
 * Historic topic of the Growroom archive, separate from current Forum topics.
 */
#[ContentEntityType(
  id: 'aculta_forum_legacy_topics',
  label: new TranslatableMarkup('Tópico histórico do Fórum'),
  handlers: [
    'access' => LegacyForumContentAccessControlHandler::class,
  ],
  base_table: 'aculta_forum_legacy_topics',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'titulo',
  ],
  admin_permission: 'administer aculta legado',
  internal: TRUE,
)]
final class LegacyForumTopic extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Stable identifier from the archive; re-running an import never duplicates.
    $fields['origem_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador de origem'))
      ->setSetting('max_length', 64)
      ->setSetting('is_ascii', TRUE)
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);
    $fields['titulo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título histórico'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);
    $fields['corpo'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Corpo histórico'));
    $fields['autor'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Autoria histórica'))
      ->setSetting('target_type', 'aculta_forum_legacy_user');
    $fields['dominio_origem'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Domínio de origem'))
      ->setSetting('max_length', 64);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));

    return $fields;
  }

}
