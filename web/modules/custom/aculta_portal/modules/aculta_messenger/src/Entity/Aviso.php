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
 * Aviso por pessoa (ACULTA Fórum, F5.2, F-D21 e política de avisos de 2026-10-16).
 *
 * Avisos não são mensagens: são notificações do sistema para uma pessoa. Um aviso por pessoa, por tipo e por
 * tópico. Respostas novas somam ao contador do mesmo aviso (agrupamento tipo + assunto). O contador do site é
 * zerado quando a pessoa lê o tópico; o contador do e-mail é zerado pelo resumo diário.
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

  /** Tipo do aviso de resposta em tópico seguido. */
  public const TIPO_RESPOSTA = 'forum_resposta';

  /** Tipo do aviso de atualização de tópico seguido (revisão editada). */
  public const TIPO_ATUALIZACAO = 'forum_atualizacao';

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
