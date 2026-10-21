<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\aculta_lms\Certificate\CourseCertificateManager;
use Drupal\aculta_lms\Controller\CertificateVerificationController;
use Drupal\aculta_lms\Hook\CertificateEntityViewHooks;
use Drupal\group\Entity\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lms\Entity\CourseStatus;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Covers certificate issuance and state changes using real LMS entities.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class CertificateLifecycleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'field', 'filter', 'image', 'media', 'options', 'text', 'entity_print',
    'entity_usage', 'aculta_image', 'flexible_permissions', 'group', 'views', 'lms',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installConfig(['group', 'lms']);
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('lms_course_status');
    $this->installEntitySchema('aculta_lms_certificate');

    $storage = FieldStorageConfig::create([
      'field_name' => 'field_course_hours',
      'entity_type' => 'group',
      'type' => 'integer',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'lms_course',
      'label' => 'Carga horária',
    ])->save();
  }

  /**
   * Tests issuance, reissuance, revocation, and retained history.
   */
  public function testEligibilityReissueAndRevocationKeepHistory(): void {
    $student = User::create(['name' => 'certificate-student', 'mail' => 'certificate@example.test', 'status' => 1]);
    $student->save();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso imutável',
      'uid' => $student->id(),
      'field_course_hours' => 14,
    ]);
    $course->save();
    $status = CourseStatus::create([
      CourseStatusInterface::COURSE_FIELD => $course->id(),
      'uid' => $student->id(),
      'status' => CourseStatusInterface::STATUS_PASSED,
      'finished' => 1_700_000_000,
    ]);
    $status->save();
    $issuer = new UserSession(['uid' => 1, 'permissions' => ['administer lms']]);
    $manager = $this->container->get('aculta_lms.course_certificate_manager');

    $first = $manager->issue($status, $issuer);
    self::assertSame('active', $first->get('state')->value);
    self::assertSame('certificate-student', $first->get('certificate_name')->value);
    self::assertSame('Curso imutável', $first->get('course_title')->value);
    self::assertSame(14, (int) $first->get('course_hours')->value);
    self::assertSame(1, (int) $first->get('sequence')->value);

    $firstId = (int) $first->id();
    $second = $manager->issue($status, $issuer);
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_certificate');
    $storage->resetCache();
    $first = $storage->load($firstId);
    self::assertSame('revoked', $first->get('state')->value);
    self::assertSame('Substituído por nova emissão', $first->get('revocation_reason')->value);
    self::assertSame((int) $first->id(), (int) $second->get('supersedes_id')->value);
    self::assertSame(2, (int) $second->get('sequence')->value);
    self::assertSame('active', $manager->loadByCode($second->get('verification_code')->value)->get('state')->value);

    $verification = new CertificateVerificationController(
      $manager,
      NULL,
      $this->container->get('date.formatter'),
      $this->container->get('string_translation'),
    );
    $certificate_build = [];
    $certificate_view_hook = new CertificateEntityViewHooks($this->createMock(\Drupal\Core\Datetime\DateFormatterInterface::class));
    $certificate_view_hook->entityView(
      $certificate_build,
      $second,
      $this->createMock(\Drupal\Core\Entity\Display\EntityViewDisplayInterface::class),
      'default',
    );
    self::assertSame(0, $certificate_build['#cache']['max-age']);
    self::assertContains('aculta_lms_certificate:' . $second->id(), $certificate_build['#cache']['tags']);
    $public = $verification->verify((string) $second->get('verification_code')->value);
    self::assertSame('certificate-student', $public['name']['#plain_text']);
    self::assertSame('Curso imutável', $public['course']['#plain_text']);
    self::assertSame('Certificado válido', (string) $public['status']['#plain_text']);
    self::assertArrayHasKey('pdf', $public);
    self::assertSame(0, $public['#cache']['max-age']);
    self::assertContains('aculta_lms_certificate:' . $second->id(), $public['#cache']['tags']);

    $manager->revoke($second, $issuer, 'Correção solicitada pela equipe');
    self::assertSame('revoked', $manager->loadByCode($second->get('verification_code')->value)->get('state')->value);
    self::assertSame('Correção solicitada pela equipe', $second->get('revocation_reason')->value);
    $revoked = $verification->verify((string) $second->get('verification_code')->value);
    self::assertSame('Certificado revogado', (string) $revoked['status']['#plain_text']);
    self::assertArrayNotHasKey('pdf', $revoked);
    self::assertArrayNotHasKey('revocation_reason', $revoked);
    self::assertCount(2, $manager->loadForStudent((int) $student->id()));
  }

  /**
   * Tests that ineligible statuses and unauthorized issuers are rejected.
   */
  public function testNonPassingStatusAndUnauthorizedIssuerAreRejected(): void {
    $student = User::create(['name' => 'certificate-student', 'mail' => 'certificate@example.test', 'status' => 1]);
    $student->save();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso',
      'uid' => $student->id(),
      'field_course_hours' => 8,
    ]);
    $course->save();
    $status = CourseStatus::create([
      CourseStatusInterface::COURSE_FIELD => $course->id(),
      'uid' => $student->id(),
      'status' => CourseStatusInterface::STATUS_PROGRESS,
      'finished' => 0,
    ]);
    $status->save();
    $manager = $this->container->get('aculta_lms.course_certificate_manager');

    try {
      $manager->issue($status, new UserSession(['uid' => 999, 'permissions' => []]));
      self::fail('An incomplete status and unauthorized issuer must be rejected.');
    }
    catch (UnprocessableEntityHttpException $exception) {
      self::assertStringContainsString('finished, approved', $exception->getMessage());
    }

    $status->setStatus(CourseStatusInterface::STATUS_PASSED);
    $status->setFinished(1_700_000_000)->save();
    try {
      $manager->issue($status, new UserSession(['uid' => 999, 'permissions' => []]));
      self::fail('An unauthorized issuer must be rejected.');
    }
    catch (AccessDeniedHttpException) {
      self::assertCount(0, $manager->loadForStudent((int) $student->id()));
    }
  }

  /** Tests a concurrent certificate issue request cannot start a second write. */
  public function testConcurrentIssueIsRejectedByStatusLock(): void {
    $student = User::create(['name' => 'certificate-student', 'mail' => 'certificate@example.test', 'status' => 1]);
    $student->save();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso',
      'uid' => $student->id(),
      'field_course_hours' => 8,
    ]);
    $course->save();
    $status = CourseStatus::create([
      CourseStatusInterface::COURSE_FIELD => $course->id(),
      'uid' => $student->id(),
      'status' => CourseStatusInterface::STATUS_PASSED,
      'finished' => 1_700_000_000,
    ]);
    $status->save();

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())
      ->method('acquire')
      ->with('aculta_lms_certificate_issue:' . $status->uuid(), 60.0)
      ->willReturn(FALSE);
    $lock->expects(self::never())->method('release');
    $manager = new CourseCertificateManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('datetime.time'),
      $this->container->get('database'),
      $this->container->get('event_dispatcher'),
      $this->container->get('logger.channel.aculta_lms'),
      $lock,
    );

    try {
      $manager->issue($status, new UserSession(['uid' => 1, 'permissions' => ['administer lms']]));
      self::fail('A concurrent issue must not create a second active certificate.');
    }
    catch (ConflictHttpException) {
      self::assertCount(0, $manager->loadForStudent((int) $student->id()));
    }
  }

}
