<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\aculta_lms\Event\CourseCompletionPublisherInterface;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\UserSession;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;
use Drupal\aculta_lms\Controller\CertificateVerificationController;
use Drupal\aculta_lms\Certificate\CertificateQrCodeProviderInterface;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies optional installation and DI registration without module-owned data.
 */
#[Group('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AcultaLmsInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'media', 'options', 'entity_print',
    'entity_usage', 'aculta_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
  }

  /**
   * Tests the module's optional service integration.
   */
  public function testInstallRegistersOnlyTheOptionalIntegrationService(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_lms'], FALSE));
    $container = \Drupal::getContainer();
    $this->assertTrue($container->get('module_handler')->moduleExists('aculta_lms'));
    $this->assertTrue($container->get('module_handler')->hasImplementations('form_lms_answer_form_alter', ['aculta_lms']));
    $this->assertInstanceOf(
      CourseCompletionPublisherInterface::class,
      $container->get('aculta_lms.course_completion_publisher'),
    );
    $route = $container->get('router.route_provider')->getRouteByName('aculta_lms.class_activity_deadline');
    $this->assertSame('/painel-administrativo/lms/turma/{group}/atividade/{activity}/prazo', $route->getPath());
    $this->assertSame(
      $container->get('aculta_lms.class_activity_deadline_manager'),
      $container->get(ClassActivityDeadlineManager::class),
    );
    $this->assertInstanceOf(
      LearningDashboardController::class,
      $container->get('aculta_lms.learning_dashboard_controller'),
    );
    $this->assertFalse($container->get('config.storage')->exists('aculta_lms.settings'));
    $this->assertFalse($container->get('module_handler')->hasImplementations('install', ['aculta_lms']));
    $this->assertFalse($container->get('module_handler')->hasImplementations('schema', ['aculta_lms']));
  }

  /**
   * Tests that archived attempts are private, immutable, and purged.
   */
  public function testAttemptArchivePersistsIsReadOnlyAndPurgesWithAccount(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('aculta_lms_attempt_archive');
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive');
    $archive = $storage->create([
      'uid' => 42,
      'group_id' => 'course-1',
      'course_status_id' => 'status-1',
      'lesson_status_id' => 'lesson-status-1',
      'lesson_id' => 'lesson-1',
      'lesson_label' => 'Aula de teste',
      'activity_id' => 'activity-1',
      'activity_label' => 'Resposta aberta',
      'answer_uuid' => 'answer-uuid',
      'event_type' => 'deleted',
      'answer_data' => '{"answer":"texto anterior"}',
      'score' => 0,
      'max_score' => 4,
      'started' => 100,
      'finished' => 200,
      'answered' => 200,
      'archived' => 300,
      'evaluated' => FALSE,
      'answer_comments' => '[]',
    ]);
    $archive->save();

    $administrator = new UserSession(['uid' => 1, 'permissions' => ['administer lms']]);
    $this->assertTrue($archive->access('view', $administrator));
    $this->assertFalse($archive->access('update', $administrator));
    $this->assertSame('{"answer":"texto anterior"}', $storage->load($archive->id())->get('answer_data')->value);

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $this->container->get('aculta_lms.attempt_archive')->purgeByUser($account);
    $this->assertNull($storage->load($archive->id()));
  }

  /**
   * Tests certificate snapshot privacy and retention after account deletion.
   */
  public function testCertificateSnapshotIsPrivateToGenericRoutesAndSurvivesAccountDeletion(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_lms_certificate');
    $student = User::create(['name' => 'certificate-student', 'mail' => 'certificate@example.test', 'status' => 1]);
    $student->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_certificate');
    $certificate = $storage->create([
      'uid' => (int) $student->id(),
      'course_id' => 17,
      'course_status_uuid' => 'status-uuid',
      'certificate_name' => 'Nome de certificado',
      'course_title' => 'Curso de teste',
      'course_hours' => 12,
      'completed' => 100,
      'issued' => 200,
      'sequence' => 1,
      'supersedes_id' => 0,
      'verification_code' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789',
      'template_version' => '1',
      'state' => 'active',
      'revoked' => 0,
      'revocation_reason' => '',
    ]);
    $certificate->save();

    $administrator = new UserSession(['uid' => 1, 'permissions' => ['administer lms']]);
    $this->assertFalse($certificate->access('view', $administrator, TRUE)->isAllowed());
    $certificate = $storage->load($certificate->id());
    try {
      $certificate->set('certificate_name', 'Tentativa de alteração')->save();
      $this->fail('The certificate snapshot must reject changes.');
    }
    catch (EntityStorageException $exception) {
      $this->assertInstanceOf(\LogicException::class, $exception->getPrevious());
      $this->assertSame('Issued certificate snapshot fields are immutable.', $exception->getPrevious()->getMessage());
    }
    $storage->resetCache();
    $student->delete();

    $this->assertSame('Nome de certificado', $this->container->get('aculta_lms.course_certificate_manager')
      ->loadByCode('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789')
      ->get('certificate_name')->value);
  }

  /**
   * Tests safe PDF output and inclusion of the generated QR image.
   */
  public function testCertificatePdfUsesEscapedSnapshotAndPrivateResponse(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('aculta_lms_certificate');
    $code = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN0123456789';
    $certificate = $this->container->get('entity_type.manager')->getStorage('aculta_lms_certificate')->create([
      'uid' => 19,
      'course_id' => 17,
      'course_status_uuid' => 'status-uuid',
      'certificate_name' => '<script>alert(1)</script>',
      'course_title' => 'Curso & pesquisa',
      'course_hours' => 12,
      'completed' => 100,
      'issued' => 200,
      'sequence' => 1,
      'supersedes_id' => 0,
      'verification_code' => $code,
      'template_version' => '1',
      'state' => 'active',
      'revoked' => 0,
      'revocation_reason' => '',
    ]);
    $certificate->save();

    $engine = $this->createMock(PrintEngineInterface::class);
    $engine->expects($this->once())
      ->method('addPage')
      ->with($this->callback(static fn (string $html): bool => str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($html, 'Curso &amp; pesquisa') && str_contains($html, 'data:image/png;base64,cXItcG5n')));
    $engine->method('getBlob')->willReturn('%PDF-test');
    $printEngines = $this->createMock(EntityPrintPluginManagerInterface::class);
    $printEngines->expects($this->once())->method('createInstance')->with('dompdf', [
      'enable_remote' => FALSE,
      'embedded_php' => FALSE,
      'disable_log' => TRUE,
    ])->willReturn($engine);
    $dateFormatter = $this->createMock(DateFormatterInterface::class);
    $dateFormatter->method('format')->willReturn('data de teste');
    $qrCodes = $this->createMock(CertificateQrCodeProviderInterface::class);
    $qrCodes->expects($this->once())->method('generate')->with($code, QrCodeFormat::Png)->willReturn('qr-png');

    $controller = new CertificateVerificationController(
      $this->container->get('aculta_lms.course_certificate_manager'),
      $printEngines,
      $dateFormatter,
      $this->container->get('string_translation'),
      $qrCodes,
    );
    $response = $controller->pdf($code);
    $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    $this->assertSame('max-age=0, no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('%PDF-test', $response->getContent());
  }

}
