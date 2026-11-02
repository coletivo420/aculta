<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Declaração xAPI 2.0 recebida e aceita pelo LRS do ACULTA (ADR-041, D-67).
 *
 * Registro bruto e somente de inserção: a declaração validada é guardada como
 * recebida. O estado da tentativa é derivado destes registros, nunca copiado
 * para outra entidade. A tentativa é referenciada de forma opaca por
 * lesson_status_id, o identificador do progresso da aula no LMS, sem
 * duplicar o estado. O vínculo final ainda depende de decisão do responsável
 * (ver docs/modules/ANALISE-LMS-CONTRIB-1-2-4.md).
 *
 * Campos que identificam o aluno (uid) e a declaração original (payload) têm
 * retenção configurável; a remoção mensal fica em serviço próprio da F3.
 */
#[ContentEntityType(
  id: 'aculta_lms_xapi_statement',
  label: new TranslatableMarkup('Declaração xAPI'),
  base_table: 'aculta_lms_xapi_statement',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  internal: TRUE,
)]
final class XapiStatement extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['statement_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador da declaração (UUID)'))
      ->setSetting('max_length', 36)
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Estudante'))
      ->setRequired(TRUE);

    $fields['registration'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Lançamento (registration)'))
      ->setSetting('max_length', 128)
      ->setDefaultValue('');

    $fields['lesson_status_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Progresso da aula no LMS (referência opaca)'));

    $fields['verb_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Verbo (IRI)'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);

    $fields['object_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Objeto (IRI)'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE);

    $fields['payload'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Declaração validada em JSON'))
      ->setRequired(TRUE);

    $fields['statement_timestamp'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Momento informado na declaração'))
      ->setDefaultValue(0);

    $fields['received'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Recebida em'));

    return $fields;
  }

}
