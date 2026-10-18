<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/** Verifies H5P access against real Group memberships and LMS course entities. */
#[RunTestsInSeparateProcesses]
final class H5PCourseAccessIntegrationTest extends KernelTestBase {

  use UserCreationTrait;

  /** {@inheritdoc} */
  protected static $modules = ['system', 'user', 'field', 'file', 'text', 'filter', 'entity', 'options', 'entity_usage', 'flexible_permissions', 'group', 'views', 'lms', 'aculta_image', 'aculta_lms', 'aculta_lms_h5p'];

  /** {@inheritdoc} */
  protected function setUpFilesystem(): void {
    mkdir($this->siteDirectory, 0775, TRUE);
    mkdir($this->siteDirectory . '/files', 0775, TRUE);
    mkdir($this->siteDirectory . '/private', 0775, TRUE);
    $this->setSetting('file_public_path', $this->siteDirectory . '/files');
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /** {@inheritdoc} */
  public function register(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', PrivateStream::class)
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /** {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'filter', 'group', 'lms']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('h5p_content');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('aculta_lms_h5p', ['h5p_content_libraries', 'h5p_points', 'h5p_content_user_data']);

    FieldStorageConfig::create([
      'field_name' => 'field_h5p_activities',
      'entity_type' => 'group',
      'type' => 'h5p',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_h5p_activities',
      'entity_type' => 'group',
      'bundle' => 'lms_course',
      'label' => 'Atividades interativas',
    ])->save();
  }

  /** Form-cache serialization restores injected widget services. */
  public function testEditorWidgetDependenciesSurviveFormSerialization(): void {
    $fieldDefinition = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('group', 'lms_course')['field_h5p_activities'];
    $widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $fieldDefinition,
      'configuration' => ['type' => 'h5p_editor'],
    ]);
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Plugin\Field\FieldWidget\H5PEditorWidget::class, $widget);

    $restored = unserialize(serialize($widget));
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Plugin\Field\FieldWidget\H5PEditorWidget::class, $restored);
    foreach ([
      'courseAccess' => 'aculta_lms_h5p.course_access',
      'libraryAccess' => 'aculta_lms_h5p.library_access',
      'currentUser' => 'current_user',
    ] as $propertyName => $serviceId) {
      $property = new \ReflectionProperty($restored, $propertyName);
      self::assertTrue($property->isInitialized($restored), $propertyName . ' is initialized after form cache restore.');
      self::assertSame($this->container->get($serviceId), $property->getValue($restored));
    }
  }

  /** An unused unlimited-field editor row passes validation while empty. */
  public function testEmptyEditorWidgetRowIsAllowed(): void {
    $owner = $this->createUser();
    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso H5P vazio', 'uid' => $owner->id()]);
    $course->save();
    $widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $course->getFieldDefinition('field_h5p_activities'),
      'configuration' => ['type' => 'h5p_editor'],
    ]);
    $formState = new \Drupal\Core\Form\FormState();
    $formState->setValue(
      ['field_h5p_activities', 0, 'h5p_content'],
      ['library' => '', 'parameters' => ''],
    );
    $element = [
      '#value' => '',
      '#parents' => ['field_h5p_activities', 0, 'h5p_content', 'parameters'],
    ];

    $widget->validateParameters($element, $formState);
    $widget->validate(['#value' => ''], $formState);

    self::assertSame([], $formState->getErrors());
  }

  /** A learner can open only H5P content referenced by a course they can take. */
  public function testPlaybackRequiresCurrentCourseMembershipAndFieldReference(): void {
    $owner = $this->createUser();
    $student = $this->createUser();
    $educator = $this->createUser();
    $outsider = $this->createUser();
    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso H5P', 'uid' => $owner->id()]);
    $course->save();

    $studentRole = GroupRole::create([
      'id' => 'lms_course-h5p_test_student',
      'label' => 'H5P test student',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['take course'],
    ]);
    $studentRole->save();
    $educatorRole = GroupRole::create([
      'id' => 'lms_course-h5p_test_educator',
      'label' => 'H5P test educator',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['edit group'],
    ]);
    $educatorRole->save();
    $course->addMember($student, ['group_roles' => [$studentRole->id()]]);
    $course->addMember($educator, ['group_roles' => [$educatorRole->id()]]);

    $content = $this->container->get('entity_type.manager')->getStorage('h5p_content')->create([
      'library_id' => 1,
      'parameters' => '{}',
      'title' => 'Atividade vinculada',
    ]);
    $content->save();
    $course->get('field_h5p_activities')->appendItem(['h5p_content_id' => $content->id()]);
    $course->save();

    $access = $this->container->get('aculta_lms_h5p.course_access');
    self::assertTrue($access->studentAccess((int) $course->id(), (int) $content->id(), $student)->isAllowed());
    self::assertFalse($access->studentAccess((int) $course->id(), (int) $content->id(), $outsider)->isAllowed());
    self::assertFalse($access->studentAccess((int) $course->id(), (int) $content->id() + 100, $student)->isAllowed());
    self::assertTrue($access->editorAccess((int) $course->id(), 0, $educator)->isAllowed());
    self::assertTrue($access->editorAccess((int) $course->id(), (int) $content->id(), $educator)->isAllowed());
    self::assertFalse($access->editorAccess((int) $course->id(), (int) $content->id(), $outsider)->isAllowed());

    // Rendering denied H5P on a private course must preserve its cache access
    // metadata without calling methods that AccessResult does not provide.
    $this->container->get('current_user')->setAccount($outsider);
    $formatter = $this->container->get('plugin.manager.field.formatter')->getInstance([
      'configuration' => [
        'type' => 'h5p_default',
        'label' => 'hidden',
        'settings' => [],
        'third_party_settings' => [],
      ],
      'field_definition' => $course->getFieldDefinition('field_h5p_activities'),
      'view_mode' => 'default',
    ]);
    $deniedElements = $formatter->viewElements($course->get('field_h5p_activities'), 'en');
    self::assertFalse($deniedElements[0]['#access']);
    self::assertContains('user', $deniedElements[0]['#cache']['contexts']);
    $this->container->get('current_user')->setAccount($student);

    $condition = $this->container->get('plugin.manager.condition')->createInstance('aculta_lms_h5p_group_has_activities');
    $condition->setContextValue('group', $course);
    self::assertTrue($condition->evaluate());
    $integration = \Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal::getGenericH5PIntegrationSettings($course);
    self::assertStringContainsString(
      '/h5p-ajax/' . $course->id() . '/content-user-data/:contentId/:dataType/:subContentId?token=',
      $integration['ajax']['contentUserData'],
    );
    self::assertMatchesRegularExpression('/^\/h5p-ajax\/' . $course->id() . '\/set-finished\.json\?token=[a-f0-9]+$/', $integration['ajax']['setFinished']);

    // Exercise the real progress endpoint and ensure state is scoped to the
    // authenticated student, course membership, and H5P content.
    $this->container->get('current_user')->setAccount($student);
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(\Drupal\aculta_lms_h5p\Controller\H5PAJAX::class);
    $token = \H5PCore::createToken('contentuserdata');
    $saveRequest = Request::create('/?token=' . $token, 'POST', ['data' => '{"answer":"saved"}']);
    $saved = $controller->contentUserData((int) $course->id(), (int) $content->id(), 'state', 0, $saveRequest);
    self::assertSame(200, $saved->getStatusCode());
    $loadRequest = Request::create('/?token=' . $token, 'GET');
    $loaded = $controller->contentUserData((int) $course->id(), (int) $content->id(), 'state', 0, $loadRequest);
    self::assertSame('{"answer":"saved"}', json_decode((string) $loaded->getContent(), TRUE)['data']);
    self::assertStringContainsString('private', (string) $loaded->headers->get('Cache-Control'));
    self::assertStringContainsString('no-store', (string) $loaded->headers->get('Cache-Control'));

    $resultToken = \H5PCore::createToken('result');
    $resultRequest = Request::create('/?token=' . $resultToken, 'POST', [
      'contentId' => $content->id(),
      'score' => 3,
      'maxScore' => 5,
      'opened' => 123456,
    ]);
    $result = $controller->setFinished((int) $course->id(), $resultRequest);
    self::assertSame(200, $result->getStatusCode());
    $score = $this->container->get('database')->select('h5p_points', 'p')
      ->fields('p', ['started', 'points', 'max_points'])
      ->condition('content_id', $content->id())
      ->condition('uid', $student->id())
      ->execute()->fetchAssoc();
    self::assertSame('123456', (string) $score['started']);
    self::assertSame('3', (string) $score['points']);
    self::assertSame('5', (string) $score['max_points']);

    $this->container->get('current_user')->setAccount($outsider);
    $denied = $controller->contentUserData((int) $course->id(), (int) $content->id(), 'state', 0, $loadRequest);
    self::assertSame(403, $denied->getStatusCode());
    $deniedResult = $controller->setFinished((int) $course->id(), $resultRequest);
    self::assertSame(403, $deniedResult->getStatusCode());
    $this->container->get('current_user')->setAccount($student);

    $course->removeMember($student);
    self::assertFalse($access->studentAccess((int) $course->id(), (int) $content->id(), $student)->isAllowed());
  }

}
