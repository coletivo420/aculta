<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Immutable issue record for an approved LMS course certificate.
 *
 * The entity stores a snapshot so later profile or course edits do not silently
 * change a certificate already issued. Only the certificate manager may
 * transition an active record to revoked; reissuance creates a new record.
 */
#[ContentEntityType(
  id: 'aculta_lms_certificate',
  label: new TranslatableMarkup('Certificado de curso'),
  base_table: 'aculta_lms_certificate',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'access' => 'Drupal\\aculta_lms\\Access\\CertificateAccessControlHandler',
  ],
  internal: TRUE,
)]
final class CourseCertificate extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Estudante'))
      ->setRequired(TRUE);
    $fields['course_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Curso'))
      ->setRequired(TRUE);
    foreach ([
      'course_status_uuid' => ['UUID do status aprovado', 128],
      'certificate_name' => ['Nome registrado no certificado', 255],
      'course_title' => ['Nome do curso registrado', 255],
      'verification_code' => ['Código público de verificação', 64],
      'template_version' => ['Versão do modelo', 32],
      'revocation_reason' => ['Motivo da revogação', 255],
    ] as $name => [$label, $max_length]) {
      $fields[$name] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup($label))
        ->setSetting('max_length', $max_length)
        ->setRequired($name !== 'revocation_reason');
    }
    foreach ([
      'course_hours' => ['Carga horária (horas)', 0],
      'completed' => ['Conclusão do curso', 0],
      'issued' => ['Emissão do certificado', 0],
      'sequence' => ['Versão da emissão', 1],
      'supersedes_id' => ['Emissão substituída', 0],
      'revoked' => ['Revogação', 0],
    ] as $name => [$label, $default]) {
      $fields[$name] = BaseFieldDefinition::create('integer')
        ->setLabel(new TranslatableMarkup($label))
        ->setRequired(TRUE)
        ->setDefaultValue($default);
    }
    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Estado'))
      ->setSetting('allowed_values', [
        'active' => 'Válido',
        'revoked' => 'Revogado',
      ])
      ->setRequired(TRUE)
      ->setDefaultValue('active');

    return $fields;
  }

}
