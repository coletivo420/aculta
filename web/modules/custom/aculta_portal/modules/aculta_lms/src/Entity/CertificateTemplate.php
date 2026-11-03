<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Modelo de certificado de curso, separado do registro emitido (ideia do Opigno, implementação própria).
 *
 * CourseCertificate é o registro emitido (estado, revogação, reemissão). Este modelo guarda
 * o desenho usado na impressão: nome, orientação do papel e modo de visualização. A ligação
 * com o curso é uma referência opaca (course_id), sem dependência do LMS contrib.
 */
#[ContentEntityType(
  id: 'aculta_lms_certificate_template',
  label: new TranslatableMarkup('Modelo de certificado'),
  base_table: 'aculta_lms_certificate_template',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  internal: TRUE,
)]
final class CertificateTemplate extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome do modelo'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);

    $fields['orientation'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Orientação do papel'))
      ->setSetting('allowed_values', ['portrait' => 'Retrato', 'landscape' => 'Paisagem'])
      ->setRequired(TRUE)
      ->setDefaultValue('landscape');

    $fields['view_mode'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Modo de visualização'))
      ->setSetting('max_length', 64)
      ->setDefaultValue('default');

    $fields['course_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Curso (referência opaca)'));

    $fields['published'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Publicado'))
      ->setDefaultValue(FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));

    return $fields;
  }

}
