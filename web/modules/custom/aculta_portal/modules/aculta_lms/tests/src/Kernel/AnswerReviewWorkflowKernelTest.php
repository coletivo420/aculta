<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_lms\Learning\AnswerPayload;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\aculta_lms\Form\AnswerReviewForm;
use Drupal\comment\CommentInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRelationshipType;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\PermissionScopeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Exercises educator answer listing, class isolation, and grading persistence.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AnswerReviewWorkflowKernelTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key', 'system', 'domain', 'user', 'file', 'image', 'media', 'options',
    'entity_print', 'entity_usage', 'aculta_image', 'aculta_file_uploader',
    'field', 'filter', 'flexible_permissions', 'group', 'views', 'text',
    'comment', 'aculta_lms',
  ];

  /**
   * Registers the production dependencies needed by the LMS container.
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

  /**
   * Prepares entity schemas and configuration for the workflow test.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'group', 'comment']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('user', ['users_data']);
    $entityTypes = [
      'user', 'comment', 'group', 'group_relationship', 'group_config_wrapper',
      'aculta_lms_lesson', 'aculta_lms_activity', 'aculta_lms_answer',
      'aculta_lms_attempt_archive',
    ];
    foreach ($entityTypes as $entityType) {
      $this->installEntitySchema($entityType);
    }
    $this->installConfig(['aculta_lms']);
    $this->createGroupType('lms_course');
    $this->createGroupType('lms_class');
    (FieldStorageConfig::load('group_relationship.group_roles') ?? FieldStorageConfig::create([
      'field_name' => 'group_roles',
      'entity_type' => 'group_relationship',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'group_role'],
      'cardinality' => -1,
    ]))->save();
    $this->createRelationshipType('lms_course', 'group_membership');
    $this->createRelationshipType('lms_class', 'group_membership');
    $this->createRelationshipType('lms_course', 'lms_classes');
    foreach (['lms_course-group_membership', 'lms_class-group_membership'] as $bundle) {
      (FieldConfig::load('group_relationship.' . $bundle . '.group_roles') ?? FieldConfig::create([
        'field_name' => 'group_roles',
        'entity_type' => 'group_relationship',
        'bundle' => $bundle,
        'label' => 'Group roles',
        'settings' => ['handler' => 'default', 'handler_settings' => []],
      ]))->save();
    }
  }

  /**
   * Verifies class-scoped visibility and educator grading behavior.
   */
  public function testEducatorSeesOnlyCurrentClassAnswersAndCanGradeWithFeedback(): void {
    $owner = User::create(['name' => 'answer-review-owner', 'status' => 1]);
    $owner->save();
    $educator = User::create(['name' => 'answer-review-teacher', 'status' => 1]);
    $educator->save();
    $student = User::create(['name' => 'answer-review-student', 'status' => 1]);
    $student->save();
    $otherStudent = User::create(['name' => 'answer-review-other', 'status' => 1]);
    $otherStudent->save();

    GroupRole::create([
      'id' => 'lms_course-answer_review_teacher',
      'label' => 'Educador de avaliação',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students'],
    ])->save();
    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso de revisão', 'uid' => $owner->id()]);
    $course->save();
    $course->addMember($educator, ['group_roles' => ['lms_course-answer_review_teacher']]);

    $class = Group::create(['type' => 'lms_class', 'label' => 'Turma de revisão', 'uid' => $owner->id()]);
    $class->save();
    $otherClass = Group::create(['type' => 'lms_class', 'label' => 'Outra turma', 'uid' => $owner->id()]);
    $otherClass->save();
    $course->addRelationship($class, 'lms_classes')->save();
    $course->addRelationship($otherClass, 'lms_classes')->save();
    $class->addMember($student);
    $otherClass->addMember($otherStudent);

    $lesson = LessonEntity::create([
      'course_id' => $course->id(),
      'title' => 'Aula de resposta aberta',
      'published' => TRUE,
      'weight' => 0,
    ]);
    $lesson->save();
    $activity = ActivityEntity::create([
      'lesson_id' => $lesson->id(),
      'type' => 'free_text',
      'graded' => TRUE,
      'weight' => 0,
      'answer_config' => '{}',
    ]);
    $activity->save();
    $answer = $this->answer($activity, $student, 'Minha resposta privada');
    $otherAnswer = $this->answer($activity, $otherStudent, 'Resposta de outra turma');

    \Drupal::currentUser()->setAccount($educator);
    $access = $this->container->get('aculta_lms.answer_review_access');
    self::assertTrue($access->access($course, $class)->isAllowed());
    self::assertTrue($access->access($course, $class, $answer)->isAllowed());
    self::assertFalse($access->access($course, $class, $otherAnswer)->isAllowed());

    $listing = $this->container->get('aculta_lms.answer_review_controller')->list($course, $class);
    self::assertCount(1, $listing['answers']['#rows']);
    self::assertSame('answer-review-student', $listing['answers']['#rows'][0][0]['data']['#plain_text']);
    self::assertSame('Aula de resposta aberta', $listing['answers']['#rows'][0][1]['data']['#plain_text']);

    $form = AnswerReviewForm::create($this->container);
    $formState = new FormState();
    $formArray = $form->buildForm([], $formState, $course, $class, $answer);
    self::assertSame('{"answer":"Minha resposta privada"}', $formArray['attempt']['payload']['#plain_text']);
    $formState->setValue('score', '87.5');
    $formState->setValue('feedback', 'Boa análise; desenvolva mais a conclusão.');
    $form->validateForm($formArray, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $form->submitForm($formArray, $formState);

    $graded = $this->container->get('entity_type.manager')->getStorage('aculta_lms_answer')->load($answer->id());
    self::assertSame('graded', $graded->get('state')->value);
    self::assertSame(87.5, (float) $graded->get('score')->value);
    self::assertSame('Boa análise; desenvolva mais a conclusão.', $graded->get('feedback')->value);
    $comments = $this->container->get('entity_type.manager')->getStorage('comment')->loadByProperties([
      'entity_type' => 'aculta_lms_answer',
      'entity_id' => $answer->id(),
      'field_name' => 'comments',
      'comment_type' => 'aculta_lms_answer_comment',
      'status' => 1,
    ]);
    self::assertCount(1, $comments);
    $comment = reset($comments);
    self::assertInstanceOf(CommentInterface::class, $comment);
    self::assertSame('Boa análise; desenvolva mais a conclusão.', $comment->get('comment_body')->value);
    self::assertSame((int) $educator->id(), (int) $comment->getOwnerId());
    self::assertStringNotContainsString('Resposta de outra turma', (string) Json::encode($listing));
  }

  /**
   * Creates and stores a submitted answer for an activity.
   */
  private function answer(ActivityEntity $activity, User $student, string $text): AnswerEntity {
    $answer = AnswerEntity::create([
      'activity_id' => $activity->id(),
      'uid' => $student->id(),
      'state' => 'submitted',
      'answer_data' => (new AnswerPayload(['answer' => $text]))->toJson(),
    ]);
    $answer->save();
    return $answer;
  }

  /**
   * Creates a Group type used by the fixture.
   */
  private function createGroupType(string $id): void {
    GroupType::create([
      'id' => $id,
      'label' => $id,
      'creator_membership' => FALSE,
      'creator_roles' => [],
    ])->save();
  }

  /**
   * Creates a Group relationship type used by the fixture.
   */
  private function createRelationshipType(string $groupType, string $pluginId): void {
    $id = $groupType . '-' . $pluginId;
    (GroupRelationshipType::load($id) ?? GroupRelationshipType::create([
      'id' => $id,
      'group_type' => $groupType,
      'relation_type' => $pluginId,
      'plugin_config' => [
        'group_cardinality' => 0,
        'entity_cardinality' => 1,
      ],
    ]))->save();
  }

}
