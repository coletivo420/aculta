<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_forum_lms\Context\LmsActivityDiscussionResolver;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\ActivityType;
use Drupal\lms\Entity\Lesson;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests activity discussion resolution with real LMS, Node, and Group storage.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsActivityDiscussionResolverKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'entity',
    'node',
    'flexible_permissions',
    'group',
    'gnode',
    'views',
    'lms',
    'lms_classes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'node', 'group', 'lms', 'lms_classes']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('lms_activity');
    $this->installEntitySchema('lms_course_status');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'aculta_lms_topic', 'name' => 'LMS discussion topic'])->save();
    $field_storage = $this->container->get('entity_type.manager')->getStorage('field_storage_config');
    $field_storage->create([
      'field_name' => 'field_lms_activity',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'lms_activity'],
      'cardinality' => 1,
    ])->save();
    $this->container->get('entity_type.manager')->getStorage('field_config')->create([
      'field_name' => 'field_lms_activity',
      'entity_type' => 'node',
      'bundle' => 'aculta_lms_topic',
      'label' => 'Atividade do curso',
      'settings' => ['handler' => 'default:lms_activity'],
    ])->save();

    $group_type_storage = $this->container->get('entity_type.manager')->getStorage('group_type');
    $relationship_type_storage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationship_type_storage);
    $course_type = $group_type_storage->load('lms_course');
    $class_type = $group_type_storage->load('lms_class');
    foreach ([
      [$course_type, 'lms_classes'],
      [$course_type, 'group_node:aculta_lms_topic'],
      [$class_type, 'group_node:aculta_lms_topic'],
    ] as [$group_type, $plugin_id]) {
      $relationship_type = $relationship_type_storage->createFromPlugin($group_type, $plugin_id);
      if (!$relationship_type_storage->load($relationship_type->id())) {
        $relationship_type_storage->save($relationship_type);
      }
    }

    $this->container->get('entity_type.manager')->getStorage('group_role')->create([
      'id' => 'lms_course-test_member',
      'label' => 'Course test member',
      'group_type' => 'lms_course',
      'scope' => 'individual',
      'permissions' => [
        'view group',
        'view group_node:aculta_lms_topic entity',
      ],
    ])->save();
    Role::load('authenticated')->grantPermission('access content')->save();
  }

  /**
   * Resolves only an accessible activity discussion in its course context.
   */
  public function testResolverRestrictsTopicsToActivityCourseAndGroupAccess(): void {
    $owner = $this->createUser();
    $student = $this->createUser();
    ActivityType::create(['id' => 'discussion_test', 'name' => 'Discussion test', 'pluginId' => 'no_answer'])->save();
    $activity = Activity::create([
      'type' => 'discussion_test',
      'name' => 'Atividade da turma',
      'status' => 1,
      'uid' => $owner->id(),
    ]);
    $activity->save();
    $lesson = Lesson::create(['name' => 'Aula com atividade', 'status' => 1, 'uid' => $owner->id()]);
    $lesson->get('activities')->appendItem(['target_id' => $activity->id()]);
    $lesson->save();

    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso da atividade', 'uid' => $owner->id()]);
    $course->get('lessons')->appendItem(['target_id' => $lesson->id()]);
    $course->save();
    $course->addMember($student, ['group_roles' => ['lms_course-test_member']]);

    $other_course = Group::create(['type' => 'lms_course', 'label' => 'Outro curso', 'uid' => $owner->id()]);
    $other_course->save();
    $topic = $this->createTopic($student, 'Discussão da atividade', $activity);
    $course->addRelationship($topic, 'group_node:aculta_lms_topic')->save();
    $other_topic = $this->createTopic($student, 'Discussão de outro curso', $activity);
    $other_course->addRelationship($other_topic, 'group_node:aculta_lms_topic')->save();
    $topic_access = $topic->access('view', $student, TRUE);
    self::assertTrue($topic_access->isAllowed(), 'The Group member has current access to the linked discussion.');

    $entity_type_manager = $this->container->get('entity_type.manager');
    $access_check = new ClassActivityDeadlineAccessCheck(
      $entity_type_manager,
      $this->container->get('current_user'),
    );
    $resolver = new LmsActivityDiscussionResolver(
      $entity_type_manager,
      new ClassActivityDeadlineManager($entity_type_manager, $access_check),
      $this->container->get('string_translation'),
    );

    $build = $resolver->build($activity, $course, $student);

    self::assertCount(1, $build['links']['#items']);
    self::assertSame('Discussão da atividade', $build['links']['#items'][0]['#title']);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
    self::assertContains('group_relationship_list:plugin:group_node:aculta_lms_topic', $build['#cache']['tags']);
  }

  /**
   * Creates a topic referencing the given activity.
   */
  private function createTopic(UserInterface $owner, string $title, Activity $activity): NodeInterface {
    $topic = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'aculta_lms_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $owner->id(),
      'field_lms_activity' => ['target_id' => $activity->id()],
    ]);
    $topic->save();
    return $topic;
  }

}
