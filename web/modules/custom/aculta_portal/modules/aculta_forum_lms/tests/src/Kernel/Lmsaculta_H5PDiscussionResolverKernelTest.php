<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_forum_lms\Context\Lmsaculta_H5PDiscussionResolver;
use Drupal\aculta_forum_lms\Hook\Lmsaculta_H5PDiscussionHooks;
use Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests H5P discussion links with real H5P, Node, and Group entities. */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class Lmsaculta_H5PDiscussionResolverKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'filter',
    'text',
    'options',
    'entity_usage',
    'entity',
    'flexible_permissions',
    'group',
    'gnode',
    'views',
    'node',
    'lms',
    'lms_classes',
    'aculta_image',
    'aculta_lms',
    'aculta_lms_h5p',
  ];

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
    $this->installConfig(['system', 'user', 'node', 'group', 'lms', 'lms_classes']);
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

    NodeType::create(['type' => 'aculta_lms_topic', 'name' => 'LMS discussion topic'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_h5p_content',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'aculta_h5p_content'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_h5p_content',
      'entity_type' => 'node',
      'bundle' => 'aculta_lms_topic',
      'label' => 'Conteúdo H5P',
    ])->save();
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

    $groupTypeStorage = $this->container->get('entity_type.manager')->getStorage('group_type');
    $relationshipTypeStorage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypeStorage);
    $courseType = $groupTypeStorage->load('lms_course');
    $classType = $groupTypeStorage->load('lms_class');
    $relationshipDefinitions = [
      [$courseType, 'group_node:aculta_lms_topic'],
      [$classType, 'group_node:aculta_lms_topic'],
      [$courseType, 'lms_classes'],
    ];
    foreach ($relationshipDefinitions as [$groupType, $pluginId]) {
      $relationshipType = $relationshipTypeStorage->createFromPlugin($groupType, $pluginId);
      if (!$relationshipTypeStorage->load($relationshipType->id())) {
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
    Role::load('authenticated')->grantPermission('access content')->save();
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

    self::assertCount(1, $build['links']['#items']);
    self::assertSame('Discussão desta atividade', $build['links']['#items'][0]['#title']);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
    self::assertContains('group_relationship_list:plugin:group_node:aculta_lms_topic', $build['#cache']['tags']);
    self::assertArrayNotHasKey('links', $resolver->build((int) $content->id(), $course, $outsider));
    self::assertArrayNotHasKey('links', $resolver->build((int) $otherContent->id(), $course, $student));
    self::assertNotContains($unattachedTopic->label(), array_column($build['links']['#items'], '#title'));
    $classBuild = $resolver->build((int) $content->id(), $course, $classMember);
    self::assertSame(['Discussão da turma'], array_column($classBuild['links']['#items'], '#title'));

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
    self::assertSame(
      (string) $course->id(),
      $topicForm['field_h5p_content']['widget'][0]['target_id']['#autocomplete_query_parameters']['group_id'],
    );

    $elements = [0 => ['#markup' => '<div class="h5p-content">Atividade</div>']];
    $hook->formatterAlter($elements, $course->get('field_h5p_activities'));
    self::assertArrayHasKey('player', $elements[0]);
    self::assertSame('lms-h5p-activity', $elements[0]['#attributes']['data-portal-ui']);
    self::assertSame('lms-h5p-discussions', $elements[0]['discussions']['#attributes']['data-portal-ui']);
    self::assertSame('Discussão desta atividade', $elements[0]['discussions']['links']['#items'][0]['#title']);
  }

  /**
   * Creates a minimal persisted H5P content entity. */
  private function createH5pContent(string $title): aculta_H5PContent {
    $content = $this->container->get('entity_type.manager')->getStorage('aculta_h5p_content')->create([
      'library_id' => 1,
      'parameters' => '{}',
      'title' => $title,
    ]);
    $content->save();
    return $content;
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
