<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Certificate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Issues, revokes and looks up certificate snapshots.
 *
 * The record deliberately stores the numeric UID and display name as snapshot
 * fields, not a user entity reference, so account deletion does not cascade to
 * an already issued, publicly verifiable certificate.
 */
final class CourseCertificateManager implements CourseCertificateManagerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly Connection $database,
  ) {}

  public function issue(CourseStatusInterface $status, AccountInterface $issuer): CourseCertificate {
    if (!$status->isFinished() || $status->getStatus() !== CourseStatusInterface::STATUS_PASSED) {
      throw new UnprocessableEntityHttpException('Only a finished, approved course status can receive a certificate.');
    }

    $course = $status->getCourse();
    $student = $status->getUser();
    if (!$course instanceof GroupInterface) {
      throw new UnprocessableEntityHttpException('The course or student is unavailable.');
    }
    if (!$issuer->hasPermission('administer lms') && !$course->hasPermission('grade students', $issuer)) {
      throw new AccessDeniedHttpException();
    }
    if (!$course->hasField('field_course_hours') || $course->get('field_course_hours')->isEmpty()) {
      throw new UnprocessableEntityHttpException('The course must have a workload in hours before issuing certificates.');
    }
    $hours = (int) $course->get('field_course_hours')->value;
    if ($hours < 1) {
      throw new UnprocessableEntityHttpException('The course workload must be greater than zero.');
    }

    $storage = $this->entityTypeManager->getStorage('aculta_lms_certificate');
    $transaction = $this->database->startTransaction();
    try {
      $previous = $storage->loadByProperties([
        'course_status_uuid' => $status->uuid(),
        'state' => 'active',
      ]);
      $sequence = 1;
      $supersedesId = 0;
      foreach ($previous as $issued_certificate) {
        $sequence = max($sequence, ((int) $issued_certificate->get('sequence')->value) + 1);
        $supersedesId = (int) $issued_certificate->id();
        $issued_certificate->set('state', 'revoked');
        $issued_certificate->set('revoked', $this->time->getRequestTime());
        $issued_certificate->set('revocation_reason', 'Substituído por nova emissão');
        $issued_certificate->save();
      }

      $certificate = $storage->create([
        'uid' => (int) $student->id(),
        'course_id' => (int) $course->id(),
        'course_status_uuid' => $status->uuid(),
        'certificate_name' => $student->getDisplayName(),
        'course_title' => $course->label(),
        'course_hours' => $hours,
        'completed' => $status->getFinished(),
        'issued' => $this->time->getRequestTime(),
        'sequence' => $sequence,
        'supersedes_id' => $supersedesId,
        'verification_code' => $this->newVerificationCode(),
        'template_version' => '1',
        'state' => 'active',
        'revoked' => 0,
        'revocation_reason' => '',
      ]);
      $certificate->save();
      unset($transaction);

      return $certificate;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function revoke(CourseCertificate $certificate, AccountInterface $issuer, string $reason): void {
    $course = $this->entityTypeManager->getStorage('group')->load($certificate->get('course_id')->value);
    if (!$issuer->hasPermission('administer lms') && (!$course instanceof GroupInterface || !$course->hasPermission('grade students', $issuer))) {
      throw new AccessDeniedHttpException();
    }
    if ($certificate->get('state')->value === 'revoked') {
      return;
    }
    $reason = trim($reason);
    if ($reason === '') {
      throw new UnprocessableEntityHttpException('A reason is required to revoke a certificate.');
    }
    $certificate->set('state', 'revoked');
    $certificate->set('revoked', $this->time->getRequestTime());
    $certificate->set('revocation_reason', mb_substr($reason, 0, 255));
    $certificate->save();
  }

  public function loadByCode(string $code): ?CourseCertificate {
    if (!preg_match('/^[A-Za-z0-9_-]{40,64}$/D', $code)) {
      return NULL;
    }
    $ids = $this->entityTypeManager->getStorage('aculta_lms_certificate')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('verification_code', $code)
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $entity = $this->entityTypeManager->getStorage('aculta_lms_certificate')->load(reset($ids));
    return $entity instanceof CourseCertificate ? $entity : NULL;
  }

  public function loadForStudent(int $uid): array {
    $ids = $this->entityTypeManager->getStorage('aculta_lms_certificate')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->sort('issued', 'DESC')
      ->execute();
    return $this->entityTypeManager->getStorage('aculta_lms_certificate')->loadMultiple($ids);
  }

  /** Generates an unallocated random verification code. */
  private function newVerificationCode(): string {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_certificate');
    do {
      $code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
      $matches = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('verification_code', $code)
        ->range(0, 1)
        ->execute();
    } while ($matches !== []);

    return $code;
  }

}
