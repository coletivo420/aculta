<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Certificate;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\aculta_lms\Entity\ProgressEntity;

/**
 * Issues and manages immutable snapshots of approved course certificates. */
interface CourseCertificateManagerInterface {

  /**
   * Issues a new certificate for an approved ACULTA progress entity.
   *
   * Any earlier active certificate for the same progress record is revoked and linked
   * from the new issue record; previous snapshots are never overwritten.
   */
  public function issue(ProgressEntity $progress, AccountInterface $issuer): CourseCertificate;

  /**
   * Revokes an active certificate while preserving its issue snapshot. */
  public function revoke(CourseCertificate $certificate, AccountInterface $issuer, string $reason): void;

  /**
   * Loads a certificate from its non-sequential public verification code. */
  public function loadByCode(string $code): ?CourseCertificate;

  /**
   * Carrega os certificados de um aluno.
   *
   * @param int $uid
   *   Identificador da conta do aluno.
   *
   * @return \Drupal\aculta_lms\Entity\CourseCertificate[]
   *   Certificados do aluno informado.
   */
  public function loadForStudent(int $uid): array;

}
