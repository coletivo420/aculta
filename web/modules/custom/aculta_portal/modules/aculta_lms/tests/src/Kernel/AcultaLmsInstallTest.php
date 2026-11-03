<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Contract\PaymentOperationsInterface;
use Drupal\aculta_lms\CourseOrderEnrollmentManagerInterface;
use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_lms\Hook\EnrollmentFormHooks;
use Drupal\aculta_lms\Hook\CourseFirstAdminMenuHooks;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\aculta_lms\Event\CourseCompletionPublisherInterface;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Learning\CourseActivityReaderInterface;
use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginResolver;
use Drupal\aculta_lms\Plugin\ActivityAnswer\SelectAnswerPlugin;
use Drupal\aculta_lms\Learning\CourseAnswerReaderInterface;
use Drupal\aculta_lms\Learning\AnswerPayload;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
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
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies optional installation and DI registration without module-owned data.
 */
#[Group('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AcultaLmsInstallTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'media', 'options', 'entity_print', 'domain',
    'entity_usage', 'key', 'aculta_image', 'aculta_file_uploader',
    'text', 'views', 'group', 'flexible_permissions', 'lms', 'lms_classes',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('lms_course_status');
    $this->installEntitySchema('lms_lesson_status');
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
    $this->assertInstanceOf(
      PaymentOperationsInterface::class,
      $container->get('aculta_portal.payment_provider.payment_operations'),
    );
    $this->assertTrue($container->get('module_handler')->hasImplementations('form_lms_answer_form_alter', ['aculta_lms']));
    $this->assertTrue($container->get('module_handler')->hasImplementations('commerce_order_insert', ['aculta_lms']));
    $this->assertTrue($container->get('module_handler')->hasImplementations('commerce_order_update', ['aculta_lms']));
    $this->assertInstanceOf(
      CourseCompletionPublisherInterface::class,
      $container->get('aculta_lms.course_completion_publisher'),
    );
    $this->assertInstanceOf(CourseLessonReaderInterface::class, $container->get(CourseLessonReaderInterface::class));
    $this->assertInstanceOf(CourseActivityReaderInterface::class, $container->get(CourseActivityReaderInterface::class));
    $objectiveAnswers = $container->get(ObjectiveAnswerPluginResolver::class);
    $this->assertInstanceOf(ObjectiveAnswerPluginResolver::class, $objectiveAnswers);
    $this->assertInstanceOf(SelectAnswerPlugin::class, $objectiveAnswers->forActivity(new CourseActivity(10, 20, 'select', TRUE)));
    $this->assertInstanceOf(CourseAnswerReaderInterface::class, $container->get(CourseAnswerReaderInterface::class));
    $this->assertInstanceOf(
      CourseOrderEnrollmentManagerInterface::class,
      $container->get('aculta_lms.course_order_enrollment'),
    );
    $checkoutSettings = $container->get('aculta_portal.checkout_settings');
    $this->assertInstanceOf(CheckoutSettings::class, $checkoutSettings);
    $this->assertSame(CheckoutSettings::LEGACY_LMS_ORDER_TYPE, $checkoutSettings->lmsOrderType());
    $this->assertInstanceOf(EnrollmentFormHooks::class, $container->get(EnrollmentFormHooks::class));
    $route = $container->get('router.route_provider')->getRouteByName('aculta_lms.class_activity_deadline');
    $this->assertSame('/painel-administrativo/lms/curso/{course}/turma/{group}/atividade/{activity}/prazo', $route->getPath());
    $dashboardRoute = $container->get('router.route_provider')->getRouteByName('aculta_lms.class_dashboard');
    $this->assertSame('/painel-administrativo/lms/curso/{course}/turma/{group}/painel', $dashboardRoute->getPath());
    $rosterRoute = $container->get('router.route_provider')->getRouteByName('aculta_lms.class_roster_settings');
    $this->assertSame('/painel-administrativo/lms/curso/{course}/turma/{group}/participantes', $rosterRoute->getPath());
    $menuLinks = [
      'system.admin_lms.lessons' => [],
      'system.admin_lms.activities' => [],
      'views_view:views.classes_admin.page' => [],
      'views_view:views.courses_admin.page' => [],
    ];
    $container->get(CourseFirstAdminMenuHooks::class)->alterMenuLinks($menuLinks);
    $this->assertSame(['views_view:views.courses_admin.page'], array_keys($menuLinks));
    $this->assertSame(
      $container->get('aculta_lms.class_activity_deadline_manager'),
      $container->get(ClassActivityDeadlineManager::class),
    );
    $this->assertInstanceOf(
      LearningDashboardController::class,
      $container->get('aculta_lms.learning_dashboard_controller'),
    );
    $this->assertTrue($container->get('config.storage')->exists('aculta_lms.settings'));
    $this->assertFalse($container->get('module_handler')->hasImplementations('install', ['aculta_lms']));
    $this->assertFalse($container->get('module_handler')->hasImplementations('schema', ['aculta_lms']));
  }

  /**
   * Tests the P2 entity update installs all schemas and is idempotent.
   */
  public function testP2LearningEntityUpdateIsIdempotent(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    aculta_lms_update_10005();
    aculta_lms_update_10005();
    aculta_lms_update_10006();
    aculta_lms_update_10006();
    require_once DRUPAL_ROOT . '/' . $this->container->get('extension.list.module')->getPath('aculta_lms') . '/aculta_lms.install';
    $schema = $this->container->get('database')->schema();
    $update_manager = $this->container->get('entity.definition_update_manager');
    $answer_fields = $this->container->get('entity_field.manager')->getBaseFieldDefinitions('aculta_lms_answer');
    $update_manager->uninstallFieldStorageDefinition($answer_fields['answer_data']);
    self::assertFalse($schema->fieldExists('aculta_lms_answer', 'answer_data'));
    aculta_lms_update_10008();
    aculta_lms_update_10008();
    self::assertTrue($schema->fieldExists('aculta_lms_answer', 'answer_data'));

    $entity_type_manager = $this->container->get('entity_type.manager');
    foreach (['aculta_lms_lesson', 'aculta_lms_activity', 'aculta_lms_answer', 'aculta_lms_progress'] as $entity_type_id) {
      $this->assertTrue($entity_type_manager->hasDefinition($entity_type_id));
      $this->assertTrue($schema->tableExists($entity_type_id));
      $handler = $entity_type_manager->getAccessControlHandler($entity_type_id);
      $entity = $entity_type_manager->getStorage($entity_type_id)->create();
      $this->assertFalse($handler->access($entity, 'view', NULL));
    }
    $this->assertTrue($schema->indexExists('aculta_lms_lesson', 'course_weight'));
    $this->assertTrue($schema->indexExists('aculta_lms_activity', 'lesson_weight'));
    $this->assertTrue($schema->indexExists('aculta_lms_answer', 'activity_uid'));
    $this->assertTrue($schema->indexExists('aculta_lms_progress', 'course_uid'));
    $progress_indexes = $this->container->get('database')->query('SHOW INDEX FROM {aculta_lms_progress}')->fetchAllAssoc('Key_name');
    $this->assertSame('0', (string) $progress_indexes['course_uid']->Non_unique);
    $answer_create = $this->container->get('database')->query('SHOW CREATE TABLE {aculta_lms_answer}')->fetchField(1);
    $progress_create = $this->container->get('database')->query('SHOW CREATE TABLE {aculta_lms_progress}')->fetchField(1);
    $this->assertStringContainsString('varchar(16) NOT NULL', $answer_create);
    $this->assertStringContainsString('varchar(32) NOT NULL', $progress_create);
    $this->assertStringContainsString('activity_id" int(11) NOT NULL', $answer_create);
    $this->assertStringContainsString('answer_data', $answer_create);

    $answer_storage = $entity_type_manager->getStorage('aculta_lms_answer');
    $attempt = $answer_storage->create([
      'activity_id' => 17,
      'uid' => 23,
      'state' => 'submitted',
      'answer_data' => (new AnswerPayload(['answer' => 'primeira tentativa']))->toJson(),
    ]);
    $attempt->save();

    $graded = $answer_storage->load($attempt->id());
    $graded->set('state', 'graded');
    $graded->set('score', 0);
    $graded->save();
    self::assertSame(['answer' => 'primeira tentativa'], $graded->getAnswerPayload()->toArray());

    $graded->setAnswerPayload(new AnswerPayload(['answer' => 'alteração proibida']));
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Tentativa enviada é imutável; crie outra resposta para uma nova tentativa.');
    $graded->save();
  }

  /**
   * Tests class permission config migration is idempotent and preserves maps.
   */
  public function testClassPermissionConfigUpdateIsIdempotent(): void {
    require_once DRUPAL_ROOT . '/' . $this->container->get('extension.list.module')->getPath('aculta_lms') . '/aculta_lms.install';
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $factory = $this->container->get('config.factory');
    $factory->getEditable('lms_classes.settings')
      ->set('class_permission_mappings', ['view group_node:aculta_lms_topic entity' => 'grade students'])
      ->set('course_permission_mappings', ['take course' => 'add students'])
      ->save(TRUE);
    $factory->getEditable('aculta_lms.settings')->delete();

    aculta_lms_update_10007();
    $expected = [
      'class_permission_mappings' => ['view group_node:aculta_lms_topic entity' => 'grade students'],
      'course_permission_mappings' => ['take course' => 'add students'],
    ];
    $settings = $factory->get('aculta_lms.settings');
    $this->assertSame($expected['class_permission_mappings'], $settings->get('class_permission_mappings'));
    $this->assertSame($expected['course_permission_mappings'], $settings->get('course_permission_mappings'));

    aculta_lms_update_10007();
    $settings = $factory->get('aculta_lms.settings');
    $this->assertSame($expected['class_permission_mappings'], $settings->get('class_permission_mappings'));
    $this->assertSame($expected['course_permission_mappings'], $settings->get('course_permission_mappings'));
  }

  /**
   * Tests that archived attempts are private, immutable, and purged.
   */
  public function testAttemptArchivePersistsIsReadOnlyAndPurgesWithAccount(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_lms_attempt_archive');
    $administratorEntity = User::create([
      'name' => 'attempt-archive-admin',
      'mail' => 'attempt-archive-admin@example.test',
      'status' => 1,
    ]);
    $administratorEntity->save();
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

    $administrator = new UserSession([
      'uid' => (int) $administratorEntity->id(),
      'permissions' => ['administer lms'],
    ]);
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
