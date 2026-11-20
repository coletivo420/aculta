<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\aculta_lms_h5p\Context\Lmsaculta_H5PDiscussionResolver;
use Drupal\aculta_lms_h5p\Hook\Lmsaculta_H5PDiscussionHooks;
use Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\aculta_lms\Kernel\AcultaLmsDependencyHarnessTrait;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests H5P discussion links with real H5P, Node, and Group entities. */
#[TestGroup('aculta_lms_h5p')]
#[RunTestsInSeparateProcesses]
final class LmsH5PDiscussionResolverKernelTest extends KernelTestBase {

  use UserCreationTrait;
  use AcultaLmsDependencyHarnessTrait;
  use LmsGroupTypesSyncTrait;
  use PortalCleanupKernelServicesTrait;

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'key',
    'domain',
    'user',
    'field',
    'file',
    'image',
    'media',
    'filter',
    'text',
    'text_with_summary',
    'options',
    'entity_usage',
    'entity',
    'flexible_permissions',
    'group',
    'gnode',
    'views',
    'node',
    'aculta_image',
    'aculta_lms',
    'aculta_lms_h5p', 'token', 'aculta_file_uploader',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
    $this->registerPortalCleanupServices($container);
  }

  /**
   * {@inheritdoc} */
  protected function setUpFilesystem(): void {
    mkdir($this->siteDirectory, 0775, TRUE);
    mkdir($this->siteDirectory . '/files', 0775, TRUE);
    mkdir($this->siteDirectory . '/private', 0775, TRUE);
    $this->setSetting('file_public_path', $this->siteDirectory . '/files');
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'node', 'group']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('aculta_h5p_content');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('aculta_lms_h5p', ['h5p_content_libraries', 'h5p_points', 'h5p_content_user_data']);
    $this->installLmsGroupTypesFromSync();

    NodeType::create(['type' => 'aculta_lms_topic', 'name' => 'LMS discussion topic'])->save();
    $contentStorage = FieldStorageConfig::load('node.field_h5p_content') ?? FieldStorageConfig::create([
      'field_name' => 'field_h5p_content',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'aculta_h5p_content'],
      'cardinality' => 1,
    ]);
    $contentStorage->save();
    $contentField = FieldConfig::load('node.aculta_lms_topic.field_h5p_content') ?? FieldConfig::create([
      'field_name' => 'field_h5p_content',
      'entity_type' => 'node',
      'bundle' => 'aculta_lms_topic',
      'label' => 'Conteúdo H5P',
    ]);
    $contentField->save();
    $activitiesStorage = FieldStorageConfig::load('group.field_h5p_activities') ?? FieldStorageConfig::create([
      'field_name' => 'field_h5p_activities',
      'entity_type' => 'group',
      'type' => 'h5p',
      'cardinality' => -1,
    ]);
    $activitiesStorage->save();
    $activitiesField = FieldConfig::load('group.lms_course.field_h5p_activities') ?? FieldConfig::create([
      'field_name' => 'field_h5p_activities',
      'entity_type' => 'group',
      'bundle' => 'lms_course',
      'label' => 'Atividades interativas',
    ]);
    $activitiesField->save();

    $groupTypeStorage = $this->container->get('entity_type.manager')->getStorage('group_type');
    $relationshipTypeStorage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypeStorage);
    $courseType = $groupTypeStorage->load('lms_course');
    $classType = $groupTypeStorage->load('lms_class');
    self::assertInstanceOf(GroupTypeInterface::class, $courseType);
    self::assertInstanceOf(GroupTypeInterface::class, $classType);
    $relationshipDefinitions = [
      [$courseType, 'group_node:aculta_lms_topic'],
      [$classType, 'group_node:aculta_lms_topic'],
      [$courseType, 'lms_classes'],
    ];
    foreach ($relationshipDefinitions as [$groupType, $pluginId]) {
      $relationshipType = $relationshipTypeStorage->createFromPlugin($groupType, $pluginId);
      $relationshipTypeId = $relationshipType->id();
      if ($relationshipTypeId === NULL || !$relationshipTypeStorage->load($relationshipTypeId)) {
        $relationshipTypeStorage->save($relationshipType);
      }
    }

    $this->container->get('entity_type.manager')->getStorage('group_role')->create([
      'id' => 'lms_course-h5p_test_member',
      'label' => 'H5P discussion test member',
      'group_type' => 'lms_course',
      'scope' => 'individual',
      'permissions' => ['take course', 'view group', 'view group_node:aculta_lms_topic entity'],
    ])->save();
    $this->container->get('entity_type.manager')->getStorage('group_role')->create([
      'id' => 'lms_class-h5p_test_member',
      'label' => 'H5P class discussion test member',
      'group_type' => 'lms_class',
      'scope' => 'individual',
      'permissions' => ['view group', 'view group_node:aculta_lms_topic entity'],
    ])->save();
    $authenticatedRole = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticatedRole);
    $authenticatedRole->grantPermission('access content')->save();
  }

  /**
   * Resolves only topics linked to the selected content and course. */
  public function testResolverEnforcesH5pContentCourseAndNodeAccess(): void {
    $this->container->get('aculta_lms_h5p.framework');
    $owner = $this->createUser();
    $student = $this->createUser();
    $outsider = $this->createUser();
    $content = $this->createH5pContent('Atividade do curso');
    $otherContent = $this->createH5pContent('Outra atividade');

    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso H5P', 'uid' => $owner->id()]);
    $course->get('field_h5p_activities')->appendItem(['h5p_content_id' => $content->id()]);
    $course->save();
    $otherCourse = Group::create(['type' => 'lms_course', 'label' => 'Outro curso', 'uid' => $owner->id()]);
    $otherCourse->get('field_h5p_activities')->appendItem(['h5p_content_id' => $content->id()]);
    $otherCourse->save();
    $course->addMember($student, ['group_roles' => ['lms_course-h5p_test_member']]);

    $classMember = $this->createUser();
    $class = Group::create(['type' => 'lms_class', 'label' => 'Turma H5P', 'uid' => $owner->id()]);
    $class->save();
    $course->addRelationship($class, 'lms_classes')->save();
    $class->addMember($classMember, ['group_roles' => ['lms_class-h5p_test_member']]);

    $validTopic = $this->createTopic($student, 'Discussão desta atividade', (int) $content->id());
    $course->addRelationship($validTopic, 'group_node:aculta_lms_topic')->save();
    $wrongContentTopic = $this->createTopic($student, 'Discussão de outro conteúdo', (int) $otherContent->id());
    $course->addRelationship($wrongContentTopic, 'group_node:aculta_lms_topic')->save();
    $wrongCourseTopic = $this->createTopic($student, 'Discussão de outro curso', (int) $content->id());
    $otherCourse->addRelationship($wrongCourseTopic, 'group_node:aculta_lms_topic')->save();
    $classTopic = $this->createTopic($classMember, 'Discussão da turma', (int) $content->id());
    $class->addRelationship($classTopic, 'group_node:aculta_lms_topic')->save();
    $unattachedTopic = $this->createTopic($student, 'Discussão sem Group', (int) $content->id());

    $resolver = new Lmsaculta_H5PDiscussionResolver(
      $this->container->get('entity_type.manager'),
      $this->container->get('string_translation'),
    );
    $build = $resolver->build((int) $content->id(), $course, $student);

    $items = $this->renderArrayOffset($this->renderArrayOffset($this->renderArrayOffset($build, '#slots'), 'content'), '#items');
    self::assertCount(1, $items);
    self::assertSame('Discussão desta atividade', $this->renderStringOffset($this->renderArrayOffset($items, 0), '#title'));
    $cache = $this->renderArrayOffset($build, '#cache');
    self::assertContains('user.group_permissions', $this->renderArrayOffset($cache, 'contexts'));
    self::assertContains('group_relationship_list:plugin:group_node:aculta_lms_topic', $this->renderArrayOffset($cache, 'tags'));
    $outsiderBuild = $resolver->build((int) $content->id(), $course, $outsider);
    self::assertSame('', $this->renderStringOffset($outsiderBuild, '#markup'));
    self::assertArrayNotHasKey('#slots', $outsiderBuild);
    $otherContentBuild = $resolver->build((int) $otherContent->id(), $course, $student);
    self::assertSame('', $this->renderStringOffset($otherContentBuild, '#markup'));
    self::assertArrayNotHasKey('#slots', $otherContentBuild);
    self::assertNotContains($unattachedTopic->label(), array_column($items, '#title'));
    $classBuild = $resolver->build((int) $content->id(), $course, $classMember);
    $classItems = $this->renderArrayOffset($this->renderArrayOffset($this->renderArrayOffset($classBuild, '#slots'), 'content'), '#items');
    self::assertSame(['Discussão da turma'], array_column($classItems, '#title'));

    $this->container->get('current_user')->setAccount($student);
    $request = Request::create('/group/' . $course->id() . '/content/create/group_node:aculta_lms_topic');
    $request->attributes->set('group', $course);
    $requestStack = new RequestStack();
    $requestStack->push($request);
    $hook = new Lmsaculta_H5PDiscussionHooks(
      $resolver,
      new aculta_H5PCourseAccessManager($this->container->get('entity_type.manager')),
      $this->container->get('current_user'),
      $this->container->get('entity_type.manager'),
      $requestStack,
    );
    $topicForm = [
      'field_h5p_content' => [
        'widget' => [0 => ['target_id' => ['#type' => 'entity_autocomplete']]],
      ],
    ];
    $hook->topicFormAlter($topicForm, new FormState());
    $topicWidget = $this->renderArrayOffset($topicForm, 'field_h5p_content');
    $topicWidget = $this->renderArrayOffset($topicWidget, 'widget');
    $topicWidget = $this->renderArrayOffset($topicWidget, 0);
    $targetIdElement = $this->renderArrayOffset($topicWidget, 'target_id');
    $queryParameters = $this->renderArrayOffset($targetIdElement, '#autocomplete_query_parameters');
    self::assertSame((string) $course->id(), $this->renderStringOffset($queryParameters, 'group_id'));

    $elements = [0 => ['#markup' => '<div class="h5p-content">Atividade</div>']];
    $hook->formatterAlter($elements, $course->get('field_h5p_activities'));
    $element = $this->renderArrayOffset($elements, 0);
    self::assertSame('portal_component', $this->renderStringOffset($element, '#theme'));
    self::assertSame('layout-section', $this->renderStringOffset($element, '#name'));
    $outerSlots = $this->renderArrayOffset($element, '#slots');
    $outerContent = $this->renderArrayOffset($outerSlots, 'content');
    self::assertArrayHasKey('player', $outerContent);
    $discussionComponent = $this->renderArrayOffset($outerContent, 'discussions');
    self::assertSame('layout-section', $this->renderStringOffset($discussionComponent, '#name'));
    $discussionSlots = $this->renderArrayOffset($discussionComponent, '#slots');
    $discussionContent = $this->renderArrayOffset($discussionSlots, 'content');
    $discussionItems = $this->renderArrayOffset($discussionContent, '#items');
    self::assertSame('Discussão desta atividade', $this->renderStringOffset($this->renderArrayOffset($discussionItems, 0), '#title'));
  }

  /**
   * Creates a minimal persisted H5P content entity. */
  private function createH5pContent(string $title): aculta_H5PContent {
    $content = $this->container->get('entity_type.manager')->getStorage('aculta_h5p_content')->create([
      'library_id' => 1,
      'parameters' => '{}',
      'title' => $title,
    ]);
    self::assertInstanceOf(aculta_H5PContent::class, $content);
    $content->save();
    return $content;
  }

  /**
   * Reads an array value and fails with context when a render structure is malformed.
   *
   * @param array<mixed> $array
   *   Render array or nested render value.
   * @param int|string $key
   *   Offset to read.
   *
   * @return array<mixed>
   *   The nested array value.
   */
  private function renderArrayOffset(array $array, int|string $key): array {
    $value = $array[$key] ?? NULL;
    self::assertIsArray($value);
    return $value;
  }

  /**
   * Reads a string value and fails when the render value is not a string.
   *
   * @param array<mixed> $array
   *   Render array or nested render value.
   * @param int|string $key
   *   Offset to read.
   */
  private function renderStringOffset(array $array, int|string $key): string {
    $value = $array[$key] ?? NULL;
    self::assertIsString($value);
    return $value;
  }

  /**
   * Creates a published LMS discussion topic referencing H5P content. */
  private function createTopic(UserInterface $owner, string $title, int $contentId): NodeInterface {
    $topic = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'aculta_lms_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $owner->id(),
      'field_h5p_content' => ['target_id' => $contentId],
    ]);
    $topic->save();
    return $topic;
  }

}
