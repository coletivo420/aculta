<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Personal notice entity (ACULTA Forum, F5.2 and F-D21).
 *
 * Notices are system notifications, not messages. One notice is stored per
 * person, type, and topic. New replies increment its counters. Reading a topic
 * clears the site counter; the daily digest clears the email counter.
 */
#[ContentEntityType(
  id: 'aculta_aviso',
  label: new TranslatableMarkup('Aviso'),
  handlers: [
    'access' => AvisoAccessControlHandler::class,
  ],
  base_table: 'aculta_aviso',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'administer aculta avisos',
  internal: TRUE,
)]
final class Aviso extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Notice type for a reply in a followed topic.
   */
  public const TIPO_RESPOSTA = 'forum_resposta';

  /**
   * Notice type for a revision of a followed topic.
   */
  public const TIPO_ATUALIZACAO = 'forum_atualizacao';

  /**
   * Defines fields for personal notices.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Base field definitions.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Destinatário'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['tipo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tipo'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);
    $fields['assunto_nid'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Assunto (tópico)'))
      ->setRequired(TRUE);
    $fields['titulo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título do assunto'))
      ->setSetting('max_length', 255);
    $fields['contador_site'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Não lidos no site'))
      ->setDefaultValue(0);
    $fields['contador_email'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Pendentes no resumo por e-mail'))
      ->setDefaultValue(0);
    $fields['ultimo_comentario'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Último comentário contado'))
      ->setDefaultValue(0);
    $fields['ultimo_post'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Última revisão de post revisionável contada'))
      ->setDefaultValue(0);
    $fields['ultimo_evento'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Última revisão contada'))
      ->setDefaultValue(0);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));
    return $fields;
  }

}
