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
 * Anexo histórico do acervo, com arquivo próprio e sem vínculo com mídia corrente.
 */
#[ContentEntityType(
  id: 'aculta_forum_legacy_attachments',
  label: new TranslatableMarkup('Anexo histórico do Fórum'),
  handlers: [
    'access' => LegacyForumContentAccessControlHandler::class,
  ],
  base_table: 'aculta_forum_legacy_attachments',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'administer aculta legado',
  internal: TRUE,
)]
final class LegacyForumAttachment extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['origem_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador de origem'))
      ->setSetting('max_length', 64)
      ->setSetting('is_ascii', TRUE)
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);
    $fields['comentario'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Comentário histórico'))
      ->setSetting('target_type', 'aculta_forum_legacy_comments');
    $fields['topico'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tópico histórico'))
      ->setSetting('target_type', 'aculta_forum_legacy_topics');
    $fields['nome'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome do arquivo'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);
    $fields['mime'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tipo MIME'))
      ->setSetting('max_length', 128);
    $fields['arquivo_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('URI do arquivo histórico'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));

    return $fields;
  }

}
