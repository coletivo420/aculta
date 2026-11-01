<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Certificate;

use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;

/** Issues and manages immutable snapshots of approved course certificates. */
interface CourseCertificateManagerInterface {

  /**
   * Issues a new certificate for an approved course status.
   *
   * Any earlier active certificate for the same status is revoked and linked
   * from the new issue record; previous snapshots are never overwritten.
   */
  public function issue(CourseStatusInterface $status, AccountInterface $issuer): CourseCertificate;

  /** Revokes an active certificate while preserving its issue snapshot. */
  public function revoke(CourseCertificate $certificate, AccountInterface $issuer, string $reason): void;

  /** Loads a certificate from its non-sequential public verification code. */
  public function loadByCode(string $code): ?CourseCertificate;

  /** @return \Drupal\aculta_lms\Entity\CourseCertificate[] */
  public function loadForStudent(int $uid): array;

}
