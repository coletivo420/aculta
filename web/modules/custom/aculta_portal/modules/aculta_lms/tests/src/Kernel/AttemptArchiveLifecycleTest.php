<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\lms\LmsKernelTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\ActivityType;
use Drupal\lms\Entity\Answer;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatus;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\lms\Entity\LessonStatus;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Exercises attempt capture against real LMS and Comment entities. */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AttemptArchiveLifecycleTest extends KernelTestBase {

  use LmsKernelTestTrait;
  use UserCreationTrait;

  protected static $modules = [
    'system',
    'user',
    'filter',
    'field',
    'flexible_permissions',
    'group',
    'views',
    'options',
    'text',
    'comment',
    'lms',
    'lms_answer_comments',
    'lms_answer_plugins',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installConfig(['system', 'comment']);
    $this->installEntitySchema('action');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('lms_activity');
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('lms_course_status');
    $this->installEntitySchema('lms_lesson_status');
    $this->installEntitySchema('lms_answer');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('aculta_lms_attempt_archive');
    $this->installConfig(['group', 'lms', 'lms_answer_comments']);
  }

  public function testAnswerChangesReevaluationAndDeletionPreserveComments(): void {
    [$student, $course_status, $lesson_status, $activity, $answer] = $this->createAttempt();
    $this->createComment($answer, (int) $student->id(), 'Comentário publicado');
    $this->createComment($answer, (int) $student->id(), 'Comentário pendente', CommentInterface::NOT_PUBLISHED);

    $answer->setData(['answer' => 'Resposta revisada']);
    $answer->save();
    $archives = $this->archivesFor($course_status, $lesson_status, $activity);
    self::assertCount(1, $archives);
    self::assertSame('answer_changed', $archives[0]->get('event_type')->value);
    self::assertSame(['answer' => 'Resposta original'], \Drupal\Component\Serialization\Json::decode($archives[0]->get('answer_data')->value));
    $comments = \Drupal\Component\Serialization\Json::decode($archives[0]->get('answer_comments')->value);
    self::assertCount(1, $comments, 'Only published comments are included in the audit copy.');
    self::assertSame('Comentário publicado', $comments[0]['body']);

    $answer->setScore(3)->setEvaluated(TRUE)->save();
    $archives = $this->archivesFor($course_status, $lesson_status, $activity);
    self::assertCount(2, $archives);
    self::assertSame('evaluation_changed', $archives[1]->get('event_type')->value);
    self::assertSame(['answer' => 'Resposta revisada'], \Drupal\Component\Serialization\Json::decode($archives[1]->get('answer_data')->value));
    self::assertSame(0, (int) $archives[1]->get('score')->value);

    $answer->delete();
    $archives = $this->archivesFor($course_status, $lesson_status, $activity);
    self::assertCount(3, $archives);
    self::assertSame('deleted', $archives[2]->get('event_type')->value);
    self::assertSame(['answer' => 'Resposta revisada'], \Drupal\Component\Serialization\Json::decode($archives[2]->get('answer_data')->value));
    self::assertSame('Comentário publicado', \Drupal\Component\Serialization\Json::decode($archives[2]->get('answer_comments')->value)[0]['body']);
  }

  public function testCourseStatusDeletionPurgesItsAttemptHistory(): void {
    [, $course_status, $lesson_status, $activity, $answer] = $this->createAttempt();
    $answer->delete();
    self::assertCount(1, $this->archivesFor($course_status, $lesson_status, $activity));

    $course_status->delete();

    self::assertCount(0, $this->archivesFor($course_status, $lesson_status, $activity));
  }

  public function testAccountDeletionPurgesItsAttemptHistory(): void {
    [$student, $course_status, $lesson_status, $activity, $answer] = $this->createAttempt();
    $answer->delete();
    self::assertCount(1, $this->archivesFor($course_status, $lesson_status, $activity));

    $student->delete();

    self::assertCount(0, $this->archivesFor($course_status, $lesson_status, $activity));
  }

  public function testCourseDeletionPurgesItsAttemptHistory(): void {
    [, $course_status, $lesson_status, $activity, $answer] = $this->createAttempt();
    $course = $course_status->getCourse();
    $answer->delete();
    self::assertCount(1, $this->archivesFor($course_status, $lesson_status, $activity));

    $course->delete();

    self::assertCount(0, $this->archivesFor($course_status, $lesson_status, $activity));
  }

  public function testHistoryAccessIsCourseScopedAndOutputIsPlainText(): void {
    [$student, $course_status, , , $answer] = $this->createAttempt('<script>alert(1)</script>Resposta');
    $course = $course_status->getCourse();
    $member_role = GroupRole::create([
      'id' => 'lms_course-test_member',
      'label' => 'Test student',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => ['take course'],
    ]);
    $member_role->save();
    $teacher_role = GroupRole::create([
      'id' => 'lms_course-test_teacher',
      'label' => 'Test teacher',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students'],
    ]);
    $teacher_role->save();
    $course->addMember($student);
    $teacher = $this->createUser();
    $course->addMember($teacher, ['group_roles' => [$teacher_role->id()]]);
    $outsider = $this->createUser();
    $this->createComment($answer, (int) $student->id(), 'Feedback <script>alert(2)</script>');
    $answer->setData(['answer' => 'Resposta atual'])->save();
    $archive = $this->archivesFor($course_status, $answer->getLessonStatus(), $answer->getActivity())[0];

    self::assertTrue($archive->access('view', $student));
    self::assertTrue($archive->access('view', $teacher));
    self::assertFalse($archive->access('view', $outsider));

    $other_course = Group::create([
      'type' => 'lms_course',
      'label' => 'Outro curso',
      'uid' => $teacher->id(),
    ]);
    $other_course->save();
    $other_archive = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive')->create([
      'uid' => $student->id(),
      'group_id' => $other_course->id(),
      'course_status_id' => 'other-status',
      'lesson_status_id' => 'other-lesson-status',
      'lesson_id' => 'other-lesson',
      'lesson_label' => 'Outra aula',
      'activity_id' => 'other-activity',
      'activity_label' => 'Outra atividade',
      'answer_uuid' => 'other-answer',
      'event_type' => 'deleted',
      'answer_data' => '{"answer":"outra resposta"}',
      'score' => 0,
      'max_score' => 1,
      'started' => 1,
      'finished' => 1,
      'answered' => 1,
      'archived' => 1,
      'evaluated' => FALSE,
      'answer_comments' => '[]',
    ]);
    $other_archive->save();
    self::assertFalse($other_archive->access('view', $student));
    self::assertFalse($other_archive->access('view', $teacher));

    $this->container->get('current_user')->setAccount($student);
    $build = [];
    $cacheability = new CacheableMetadata();
    $this->container->get('module_handler')->alter('lms_answer_details', $build, $answer, $cacheability);
    self::assertArrayHasKey('previous_attempts', $build);
    $rows = $build['previous_attempts']['history']['#rows'];
    $plain_text = [];
    foreach ($rows as $row) {
      foreach ($row as $cell) {
        if (isset($cell['data']['#plain_text'])) {
          $plain_text[] = $cell['data']['#plain_text'];
        }
      }
    }
    self::assertContains('alert(1)Resposta', $plain_text);
    self::assertContains('Feedback alert(2)', $plain_text);
    self::assertContains('user', $cacheability->getCacheContexts());
    self::assertContains('user.group_permissions', $cacheability->getCacheContexts());
  }

  /** @return array{0: \Drupal\user\UserInterface, 1: CourseStatus, 2: LessonStatus, 3: Activity, 4: Answer} */
  private function createAttempt(string $answer_text = 'Resposta original'): array {
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $this->setCurrentUser($owner);
    ActivityType::create(['id' => 'archive_test', 'name' => 'Teste de arquivo', 'pluginId' => 'no_answer'])->save();
    $activity = Activity::create([
      'type' => 'archive_test',
      'name' => 'Resposta aberta',
      'status' => 1,
      'uid' => $owner->id(),
    ]);
    $activity->save();
    $lesson = Lesson::create(['name' => 'Aula de teste', 'status' => 1, 'uid' => $owner->id()]);
    $lesson->get('activities')->appendItem([
      'target_id' => $activity->id(),
      'data' => ['max_score' => 4],
    ]);
    $lesson->save();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso de teste',
      'uid' => $owner->id(),
      'lessons' => [['target_id' => $lesson->id()]],
    ]);
    $course->save();
    $course_status = CourseStatus::create([
      CourseStatusInterface::COURSE_FIELD => $course->id(),
      'uid' => $student->id(),
    ]);
    $course_status->save();
    $lesson_status = LessonStatus::create([
      'course_status' => $course_status->id(),
      LessonStatusInterface::LESSON_FIELD => $lesson->id(),
      'started' => 100,
    ]);
    $lesson_status->setActivityIds([$activity->id()]);
    $lesson_status->save();
    $answer = Answer::create([
      'user_id' => $student->id(),
      'lesson_status' => $lesson_status->id(),
      AnswerInterface::ACTIVITY_FIELD => ['target_id' => $activity->id()],
      'score' => 0,
      'evaluated' => FALSE,
    ]);
    $answer->setData(['answer' => $answer_text]);
    $answer->save();

    return [$student, $course_status, $lesson_status, $activity, $answer];
  }

  private function createComment(AnswerInterface $answer, int $uid, string $body, int $status = CommentInterface::PUBLISHED): void {
    Comment::create([
      'comment_type' => 'lms_answer_comment',
      'entity_type' => 'lms_answer',
      'entity_id' => $answer->id(),
      'field_name' => 'comments',
      'uid' => $uid,
      'status' => $status,
      'subject' => 'Feedback',
      'comment_body' => ['value' => $body, 'format' => 'plain_text'],
    ])->save();
  }

  /** @return \Drupal\aculta_lms\Entity\AttemptArchive[] */
  private function archivesFor(CourseStatusInterface $course_status, LessonStatusInterface $lesson_status, Activity $activity): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('course_status_id', (string) $course_status->id())
      ->condition('lesson_status_id', (string) $lesson_status->id())
      ->condition('activity_id', (string) $activity->id())
      ->sort('archived', 'ASC')
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

}
