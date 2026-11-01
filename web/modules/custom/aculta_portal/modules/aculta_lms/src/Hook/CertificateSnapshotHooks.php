<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/** Enforces append-only certificate snapshots outside the manager service. */
final class CertificateSnapshotHooks {

  /**
   * Prevents edits to issued snapshots and permits one-way revocation only.
   *
   * @throws \LogicException
   *   When code attempts to alter an issued snapshot or revive a revoked one.
   */
  #[Hook('entity_presave')]
  public function preserveSnapshot(EntityInterface $entity): void {
    if (!$entity instanceof CourseCertificate || $entity->isNew() || !$entity->getOriginal() instanceof CourseCertificate) {
      return;
    }

    $original = $entity->getOriginal();
    foreach ([
      'uid',
      'course_id',
      'course_status_uuid',
      'certificate_name',
      'course_title',
      'course_hours',
      'completed',
      'issued',
      'sequence',
      'supersedes_id',
      'verification_code',
      'template_version',
    ] as $field) {
      if ($entity->get($field)->value != $original->get($field)->value) {
        throw new \LogicException('Issued certificate snapshot fields are immutable.');
      }
    }

    $old_state = (string) $original->get('state')->value;
    $new_state = (string) $entity->get('state')->value;
    if ($old_state === 'revoked') {
      foreach (['state', 'revoked', 'revocation_reason'] as $field) {
        if ($entity->get($field)->value != $original->get($field)->value) {
          throw new \LogicException('A revoked certificate cannot be changed or reactivated.');
        }
      }
      return;
    }
    if ($old_state !== 'active' || !in_array($new_state, ['active', 'revoked'], TRUE)) {
      throw new \LogicException('Certificate state transition is invalid.');
    }
    if ($new_state === 'active' && ($entity->get('revoked')->value != $original->get('revoked')->value || $entity->get('revocation_reason')->value != $original->get('revocation_reason')->value)) {
      throw new \LogicException('Active certificate revocation metadata cannot be edited.');
    }
    if ($new_state === 'revoked' && ((int) $entity->get('revoked')->value < 1 || trim((string) $entity->get('revocation_reason')->value) === '')) {
      throw new \LogicException('Certificate revocation requires a timestamp and reason.');
    }
  }

}
