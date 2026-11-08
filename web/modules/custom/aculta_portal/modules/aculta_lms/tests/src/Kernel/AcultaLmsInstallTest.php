<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Contract\PaymentOperationsInterface;
use Drupal\aculta_lms\CourseOrderEnrollmentManagerInterface;
use Drupal\aculta_lms\Access\AnswerAttachmentAccessCheck;
use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_lms\Controller\CourseLessonController;
use Drupal\aculta_lms\Hook\EnrollmentFormHooks;
use Drupal\aculta_lms\Hook\CourseFirstAdminMenuHooks;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\aculta_lms\Event\CourseCompletionPublisherInterface;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Form\AnswerSubmissionForm;
use Drupal\aculta_lms\Form\ActivityDeleteForm;
use Drupal\aculta_lms\Form\CourseStructureMoveForm;
use Drupal\aculta_lms\Form\CourseStructureOrderForm;
use Drupal\aculta_lms\Form\LessonDeleteForm;
use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_lms\Learning\CourseActivityReaderInterface;
use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginResolver;
use Drupal\aculta_lms\Plugin\ActivityAnswer\SelectAnswerPlugin;
use Drupal\aculta_lms\Plugin\ActivityAnswer\FreeTextReviewFeedback;
use Drupal\aculta_lms\Learning\CourseAnswerReaderInterface;
use Drupal\aculta_lms\Learning\AnswerPayload;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Order\CourseStructureOrderManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\UserSession;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;
use Drupal\aculta_lms\Controller\CertificateVerificationController;
use Drupal\aculta_lms\Certificate\CertificateQrCodeProviderInterface;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use Drupal\group\Entity\Group as GroupEntity;
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
    'system', 'user', 'file', 'image', 'media', 'field', 'filter', 'editor', 'ckeditor5', 'comment', 'options', 'entity_print', 'domain',
    'entity_usage', 'key', 'aculta_image', 'aculta_file_uploader',
    'text', 'views', 'group', 'flexible_permissions',
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
    // Não cria os group types: a config do aculta_lms os fornece e a
    // instalação falharia com PreExistingConfigException.
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'field', 'comment', 'filter', 'group']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
  }

  /**
   * Tests the module's optional service integration.
   */
  public function testInstallRegistersOnlyTheOptionalIntegrationService(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_lms'], TRUE));
    $this->prepareAcultaLmsClassRelationship();
    $container = \Drupal::getContainer();
    $this->assertTrue($container->get('module_handler')->moduleExists('aculta_lms'));
    foreach (['courses', 'courses_catalog', 'courses_admin', 'classes_admin'] as $view_id) {
      $this->assertNotNull($container->get('entity_type.manager')->getStorage('view')->load($view_id));
    }
    $lessonFormat = $container->get('entity_type.manager')->getStorage('filter_format')->load('aculta_lms_lesson');
    $this->assertNotNull($lessonFormat);
    $this->assertSame('Conteúdo de aula ACULTA', $lessonFormat->label());
    $this->assertNotNull($container->get('config.factory')->get('editor.editor.aculta_lms_lesson')->get('editor'));
    $definition_update_manager = $container->get('entity.definition_update_manager');
    $body_definition = $definition_update_manager->getFieldStorageDefinition('body', 'aculta_lms_lesson');
    $this->assertNotNull($body_definition);
    $definition_update_manager->uninstallFieldStorageDefinition($body_definition);
    aculta_lms_update_10015();
    $lesson_fields = $container->get('entity_field.manager')->getFieldStorageDefinitions('aculta_lms_lesson');
    $this->assertSame('text_long', $lesson_fields['body']->getType());
    $course = GroupEntity::create(['type' => 'lms_course', 'label' => 'Curso com conteúdo']);
    $course->save();
    $lesson = $container->get('entity_type.manager')->getStorage('aculta_lms_lesson')->create([
      'course_id' => (int) $course->id(),
      'title' => 'Leitura de teste',
      'body' => ['value' => '<script>window.pwned = true</script>', 'format' => 'plain_text'],
      'published' => TRUE,
      'weight' => 0,
    ]);
    $lesson->save();
    $page = (new CourseLessonController(
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.lms_friendly_slugs'),
      $container->get('aculta_lms.notice_eligibility'),
      $container->get('string_translation'),
    ))->view($course, 0);
    $this->assertSame('<script>window.pwned = true</script>', $page['body']['#text']);
    $this->assertSame('plain_text', $page['body']['#format']);
    $this->assertSame('processed_text', $page['body']['#type']);
    $rendered_body = (string) $container->get('renderer')->renderRoot($page['body']);
    $this->assertStringNotContainsString('<script>', $rendered_body);
    $this->assertStringContainsString('window.pwned', $rendered_body);
    $richLesson = $container->get('entity_type.manager')->getStorage('aculta_lms_lesson')->create([
      'course_id' => (int) $course->id(),
      'title' => 'Aula rica',
      'body' => [
        'value' => '<p>Conteúdo <strong>formatado</strong></p><script>window.pwned = true</script>',
        'format' => 'aculta_lms_lesson',
      ],
      'published' => TRUE,
      'weight' => 1,
    ]);
    $richLesson->save();
    $richPage = (new CourseLessonController(
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.lms_friendly_slugs'),
      $container->get('aculta_lms.notice_eligibility'),
      $container->get('string_translation'),
    ))->view($course, 1);
    $renderedRichBody = (string) $container->get('renderer')->renderRoot($richPage['body']);
    $this->assertStringContainsString('<strong>formatado</strong>', $renderedRichBody);
    $this->assertStringNotContainsString('<script>', $renderedRichBody);
    $this->assertStringContainsString('window.pwned', $renderedRichBody);
    $activity = $container->get('entity_type.manager')->getStorage('aculta_lms_activity')->create([
      'lesson_id' => (int) $richLesson->id(),
      'type' => 'free_text',
      'graded' => TRUE,
      'weight' => 0,
      'answer_config' => '{"question":"Explique o conceito."}',
    ]);
    $activity->save();
    $activityPage = $container->get('aculta_lms.course_lesson_controller')->view($course, 1);
    $renderedActivities = (string) $container->get('renderer')->renderRoot($activityPage['activities']);
    $this->assertStringContainsString('Explique o conceito.', $renderedActivities);
    $this->assertStringContainsString('Abrir atividade', $renderedActivities);
    foreach (['lms', 'lms_classes', 'lms_answer_plugins', 'lms_answer_comments'] as $contrib_module) {
      $this->assertFalse($container->get('module_handler')->moduleExists($contrib_module));
    }
    $this->assertInstanceOf(
      PaymentOperationsInterface::class,
      $container->get('aculta_portal.payment_provider.payment_operations'),
    );
    $this->assertTrue($container->get('module_handler')->hasImplementations('form_aculta_lms_answer_submission_alter', ['aculta_lms']));
    $this->assertTrue($container->get('module_handler')->hasImplementations('commerce_order_insert', ['aculta_lms']));
    $this->assertTrue($container->get('module_handler')->hasImplementations('commerce_order_update', ['aculta_lms']));
    $attachmentRoute = $container->get('router.route_provider')->getRouteByName('aculta_lms.answer_attachment_download');
    $this->assertSame('/resposta/{answer}/arquivo', $attachmentRoute->getPath());
    $this->assertSame('entity:aculta_lms_answer', $attachmentRoute->getOption('parameters')['answer']['type']);
    $this->assertInstanceOf(
      AnswerAttachmentAccessCheck::class,
      $container->get('aculta_lms.answer_attachment_access'),
    );
    $submissionRoute = $container->get('router.route_provider')->getRouteByName('aculta_lms.answer_submission');
    $this->assertSame('/curso/{group}/{lesson_delta}/{activity_delta}', $submissionRoute->getPath());
    $this->assertSame('courses', $submissionRoute->getOption('_aculta_domain_purpose'));
    $this->assertInstanceOf(FreeTextReviewFeedback::class, $container->get(FreeTextReviewFeedback::class));
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
    $this->assertTrue($rosterRoute->getOption('no_cache'));
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
   * Ensures injected form services survive Form API cache serialization.
   */
  public function testAnswerSubmissionFormRestoresInjectedServicesAfterSerialization(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_lms'], TRUE));
    $this->prepareAcultaLmsClassRelationship();
    $form = AnswerSubmissionForm::create($this->container);
    $restored = unserialize(serialize($form), ['allowed_classes' => [AnswerSubmissionForm::class]]);

    $currentRoute = new \ReflectionProperty(AnswerSubmissionForm::class, 'currentRoute');
    $this->assertTrue($currentRoute->isInitialized($restored));
    $this->assertInstanceOf(CurrentRouteMatch::class, $currentRoute->getValue($restored));
  }

  /**
   * Ensures course-builder Form API services survive cache serialization.
   */
  public function testCourseBuilderFormsRestoreInjectedServicesAfterSerialization(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_lms'], TRUE));
    $forms = [
      ActivityDeleteForm::class => [
        'entityTypeManager' => EntityTypeManagerInterface::class,
        'currentRouteMatch' => CurrentRouteMatch::class,
        'accessCheck' => CourseAuthoringAccessCheck::class,
      ],
      LessonDeleteForm::class => [
        'entityTypeManager' => EntityTypeManagerInterface::class,
        'currentRouteMatch' => CurrentRouteMatch::class,
        'accessCheck' => CourseAuthoringAccessCheck::class,
      ],
      CourseStructureMoveForm::class => [
        'currentRouteMatch' => CurrentRouteMatch::class,
        'accessCheck' => CourseAuthoringAccessCheck::class,
        'orderManager' => CourseStructureOrderManager::class,
      ],
      CourseStructureOrderForm::class => [
        'routeMatcher' => CurrentRouteMatch::class,
        'accessCheck' => CourseAuthoringAccessCheck::class,
        'orderManager' => CourseStructureOrderManager::class,
        'entityTypeManager' => EntityTypeManagerInterface::class,
      ],
    ];

    foreach ($forms as $class => $properties) {
      $form = $class::create($this->container);
      $restored = unserialize(serialize($form), ['allowed_classes' => [$class]]);
      foreach ($properties as $propertyName => $propertyClass) {
        $property = new \ReflectionProperty($class, $propertyName);
        $this->assertTrue($property->isInitialized($restored), $class . '::$' . $propertyName . ' is initialized.');
        $this->assertInstanceOf($propertyClass, $property->getValue($restored), $class . '::$' . $propertyName . ' is restored.');
      }
    }
  }

  /**
   * Ensures moves reorder siblings without changing answer references.
   */
  public function testCourseStructureMovesAndReordersLessonsAndActivitiesWithoutChangingAnswers(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], TRUE);
    $this->prepareAcultaLmsClassRelationship();
    $course = GroupEntity::create(['type' => 'lms_course', 'label' => 'Curso de ordenação']);
    $course->save();
    $lessonStorage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_lesson');
    $lessonA = $lessonStorage->create([
      'course_id' => (int) $course->id(),
      'title' => 'Aula A',
      'published' => TRUE,
      'weight' => 0,
    ]);
    $lessonA->save();
    $lessonB = $lessonStorage->create([
      'course_id' => (int) $course->id(),
      'title' => 'Aula B',
      'published' => TRUE,
      'weight' => 9,
    ]);
    $lessonB->save();

    $activityStorage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_activity');
    $activityA = $activityStorage->create([
      'lesson_id' => (int) $lessonA->id(),
      'type' => 'free_text',
      'graded' => TRUE,
      'weight' => 0,
      'answer_config' => '{"question":"A"}',
    ]);
    $activityA->save();
    $activityB = $activityStorage->create([
      'lesson_id' => (int) $lessonA->id(),
      'type' => 'free_text',
      'graded' => TRUE,
      'weight' => 7,
      'answer_config' => '{"question":"B"}',
    ]);
    $activityB->save();
    $student = User::create(['name' => 'lms-order-student', 'status' => 1]);
    $student->save();
    $answer = $this->container->get('entity_type.manager')->getStorage('aculta_lms_answer')->create([
      'activity_id' => (int) $activityA->id(),
      'uid' => (int) $student->id(),
      'state' => 'submitted',
      'answer_data' => '{}',
    ]);
    $answer->save();

    $manager = $this->container->get('aculta_lms.course_structure_order');
    $this->assertInstanceOf(CourseStructureOrderManager::class, $manager);
    $this->assertFalse($manager->move('aculta_lms_lesson', (int) $course->id(), (int) $lessonA->id(), 'up'));
    $this->assertTrue($manager->move('aculta_lms_lesson', (int) $course->id(), (int) $lessonB->id(), 'up'));
    $this->assertSame(1, (int) $lessonStorage->load($lessonA->id())->get('weight')->value);
    $this->assertSame(0, (int) $lessonStorage->load($lessonB->id())->get('weight')->value);
    $this->assertTrue($manager->reorder('aculta_lms_lesson', (int) $course->id(), [
      (int) $lessonA->id(),
      (int) $lessonB->id(),
    ]));
    $this->assertSame(0, (int) $lessonStorage->load($lessonA->id())->get('weight')->value);
    $this->assertSame(1, (int) $lessonStorage->load($lessonB->id())->get('weight')->value);
    $this->assertFalse($manager->reorder('aculta_lms_lesson', (int) $course->id(), [
      (int) $lessonA->id(),
      (int) $lessonB->id(),
    ]));

    $this->assertTrue($manager->move('aculta_lms_activity', (int) $lessonA->id(), (int) $activityA->id(), 'down'));
    $this->assertSame(0, (int) $activityStorage->load($activityB->id())->get('weight')->value);
    $this->assertSame(1, (int) $activityStorage->load($activityA->id())->get('weight')->value);
    $this->assertTrue($manager->reorder('aculta_lms_activity', (int) $lessonA->id(), [
      (int) $activityA->id(),
      (int) $activityB->id(),
    ]));
    $this->assertSame(0, (int) $activityStorage->load($activityA->id())->get('weight')->value);
    $this->assertSame(1, (int) $activityStorage->load($activityB->id())->get('weight')->value);
    $answerStorage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_answer');
    $this->assertSame((int) $activityA->id(), (int) $answerStorage->load($answer->id())->get('activity_id')->value);
    $this->assertTrue($manager->move('aculta_lms_activity', (int) $lessonA->id(), (int) $activityA->id(), 'down'));
    $this->assertFalse($manager->move('aculta_lms_activity', (int) $lessonA->id(), (int) $activityA->id(), 'down'));
    try {
      $manager->reorder('aculta_lms_lesson', (int) $course->id(), [(int) $lessonA->id()]);
      $this->fail('An incomplete lesson permutation must be rejected.');
    }
    catch (\InvalidArgumentException) {
      $this->addToAssertionCount(1);
    }
  }

  /**
   * Tests the P2 entity update installs all schemas and is idempotent.
   */
  public function testP2LearningEntityUpdateIsIdempotent(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], TRUE);
    $this->prepareAcultaLmsClassRelationship();
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
    $entity_type_manager->getStorage('field_config')->load('aculta_lms_answer.aculta_lms_answer.comments')->delete();
    aculta_lms_update_10009();
    aculta_lms_update_10009();
    self::assertNotNull($entity_type_manager->getStorage('field_storage_config')->load('aculta_lms_answer.comments'));
    self::assertNotNull($entity_type_manager->getStorage('field_config')->load('aculta_lms_answer.aculta_lms_answer.comments'));
    self::assertNotNull($entity_type_manager->getStorage('comment_type')->load('aculta_lms_answer_comment'));
    self::assertNotNull($entity_type_manager->getStorage('field_config')->load('comment.aculta_lms_answer_comment.comment_body'));
    $entity_type_manager->getStorage('field_config')->load('comment.aculta_lms_answer_comment.comment_body')->delete();
    aculta_lms_update_10010();
    aculta_lms_update_10010();
    self::assertSame('2d34480d-1f59-4d31-86c2-829ef03786ac', $entity_type_manager->getStorage('field_config')->load('comment.aculta_lms_answer_comment.comment_body')->uuid());

    $archive_fields = $this->container->get('entity_field.manager')->getBaseFieldDefinitions('aculta_lms_attempt_archive');
    $update_manager->uninstallFieldStorageDefinition($archive_fields['score_precise']);
    self::assertFalse($schema->fieldExists('aculta_lms_attempt_archive', 'score_precise'));
    aculta_lms_update_10011();
    aculta_lms_update_10011();
    self::assertTrue($schema->fieldExists('aculta_lms_attempt_archive', 'score_precise'));

    $relationship_config = $this->container->get('config.factory')
      ->getEditable('group.relationship_type.lms_course-lms_classes');
    $relationship_config->set('dependencies', [
      'config' => ['group.type.lms_course'],
      'module' => ['lms', 'lms_classes'],
    ])->save();
    aculta_lms_update_10012();
    aculta_lms_update_10012();
    self::assertSame(['aculta_lms'], $this->container->get('config.factory')
      ->get('group.relationship_type.lms_course-lms_classes')->get('dependencies.module'));
    self::assertSame(['group.type.lms_course'], $this->container->get('config.factory')
      ->get('group.relationship_type.lms_course-lms_classes')->get('dependencies.config'));

    $activity_fields = $this->container->get('entity_field.manager')->getBaseFieldDefinitions('aculta_lms_activity');
    $update_manager->uninstallFieldStorageDefinition($activity_fields['answer_config']);
    self::assertFalse($schema->fieldExists('aculta_lms_activity', 'answer_config'));
    aculta_lms_update_10013();
    aculta_lms_update_10013();
    self::assertTrue($schema->fieldExists('aculta_lms_activity', 'answer_config'));

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
    $activity_create = $this->container->get('database')->query('SHOW CREATE TABLE {aculta_lms_activity}')->fetchField(1);
    $this->assertStringContainsString('answer_config', $activity_create);

    $answer_storage = $entity_type_manager->getStorage('aculta_lms_answer');
    $attempt = $answer_storage->create([
      'activity_id' => 17,
      'uid' => 23,
      'state' => 'submitted',
      'answer_data' => (new AnswerPayload(['answer' => 'primeira tentativa']))->toJson(),
    ]);
    $attempt->save();

    $comment = $this->container->get('entity_type.manager')->getStorage('comment')->create([
      'entity_type' => 'aculta_lms_answer',
      'entity_id' => $attempt->id(),
      'field_name' => 'comments',
      'comment_type' => 'aculta_lms_answer_comment',
      'comment_body' => ['value' => 'Feedback do educador', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $comment->save();
    self::assertSame('aculta_lms_answer', $comment->getCommentedEntity()->getEntityTypeId());

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
    $this->container->get('module_installer')->install(['aculta_lms'], TRUE);
    $this->prepareAcultaLmsClassRelationship();
    $factory = $this->container->get('config.factory');
    // Seed former contrib config as raw storage: its schema is intentionally
    // unavailable in this kernel because no contrib LMS module is enabled.
    $this->container->get('config.storage')->write('lms_classes.settings', [
      'class_permission_mappings' => ['view group_node:aculta_lms_topic entity' => 'grade students'],
      'course_permission_mappings' => ['take course' => 'add students'],
    ]);
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
    $this->container->get('module_installer')->install(['aculta_lms'], TRUE);
    $this->prepareAcultaLmsClassRelationship();
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
    $this->assertFalse($archive->access('view', $administrator), 'The former contrib administrator permission does not bypass course membership.');
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
    $this->container->get('module_installer')->install(['aculta_lms'], TRUE);
    $this->prepareAcultaLmsClassRelationship();
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
    $this->container->get('module_installer')->install(['aculta_lms'], TRUE);
    $this->prepareAcultaLmsClassRelationship();
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
