<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\file\Entity\File;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\lms\LmsKernelTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\ActivityType;
use Drupal\lms\Entity\Answer;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatus;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\lms\Entity\LessonStatus;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\aculta_lms\Controller\AnswerFileDownloadController;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Exercises attempt capture against real LMS and Comment entities. */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AttemptArchiveLifecycleTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  use LmsKernelTestTrait;
  use UserCreationTrait;

  /**
   * Modules required by the lifecycle integration tests.
   *
   * @var string[]
   */
  protected static $modules = [
    'key',
    'system', 'domain',
    'user',
    'file',
    'image',
    'media',
    'entity_usage',
    'media_duplicates',
    'aculta_image', 'aculta_file_uploader',
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

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', PrivateStream::class)
      ->addTag('stream_wrapper', ['scheme' => 'private']);
    $this->registerLmsDependencyServices($container);
  }

  /**
   * {@inheritdoc} */
  protected function setUpFilesystem(): void {
    mkdir($this->siteDirectory, 0775);
    mkdir($this->siteDirectory . '/files', 0775);
    mkdir($this->siteDirectory . '/private', 0775);
    mkdir($this->siteDirectory . '/files/config/sync', 0775, TRUE);
    $this->setSetting('file_public_path', $this->siteDirectory . '/files');
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
    $this->setSetting('config_sync_directory', $this->siteDirectory . '/files/config/sync');
  }

  /**
   * Installs schemas and configuration used by the real LMS entities.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'comment']);
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('action');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['file']);
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

  /** Archives ACULTA attempts when grading changes or the attempt is deleted. */
  public function testAcultaAttemptHistoryPreservesExactGradeAndComment(): void {
    $student = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso de teste ACULTA',
      'uid' => $student->id(),
      'status' => TRUE,
    ]);
    $course->save();
    $lesson = LessonEntity::create([
      'course_id' => (int) $course->id(),
      'title' => 'Aula de teste',
      'published' => TRUE,
      'weight' => 0,
    ]);
    $lesson->save();
    $activity = ActivityEntity::create([
      'lesson_id' => (int) $lesson->id(),
      'type' => 'free_text',
      'graded' => TRUE,
      'weight' => 0,
    ]);
    $activity->save();
    $answer = AnswerEntity::create([
      'activity_id' => (int) $activity->id(),
      'uid' => (int) $student->id(),
      'state' => 'submitted',
      'answer_data' => Json::encode((object) ['answer' => 'Resposta original']),
    ]);
    $answer->save();

    $comment = Comment::create([
      'entity_type' => 'aculta_lms_answer',
      'entity_id' => $answer->id(),
      'field_name' => 'comments',
      'comment_type' => 'aculta_lms_answer_comment',
      'comment_body' => ['value' => 'Comentário publicado ACULTA', 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
      'uid' => $student->id(),
    ]);
    $comment->save();

    $unchanged = $this->container->get('entity_type.manager')->getStorage('aculta_lms_answer')->loadUnchanged($answer->id());
    foreach (['activity_id', 'uid', 'answer_data', 'created'] as $immutable_field) {
      self::assertSame((string) $unchanged->get($immutable_field)->value, (string) $answer->get($immutable_field)->value, $immutable_field);
    }
    $answer->set('state', 'graded')->set('score', 80.25)->save();
    $archives = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive')
      ->loadByProperties(['uid' => $student->id(), 'activity_id' => (string) $activity->id()]);
    self::assertCount(1, $archives);
    $first = reset($archives);
    self::assertSame('evaluation_changed', $first->get('event_type')->value);
    self::assertSame(0.0, (float) $first->get('score_precise')->value);
    self::assertSame('Resposta original', Json::decode($first->get('answer_data')->value)['answer']);
    self::assertSame('Comentário publicado ACULTA', Json::decode($first->get('answer_comments')->value)[0]['body']);

    $answer->set('score', 91.75)->save();
    $archives = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive')
      ->loadByProperties(['uid' => $student->id(), 'activity_id' => (string) $activity->id()]);
    self::assertCount(2, $archives);
    $second = array_values($archives)[1];
    self::assertSame(80.25, (float) $second->get('score_precise')->value);
    self::assertSame(100, (int) $second->get('max_score')->value);

    $answer->delete();
    $archives = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive')
      ->loadByProperties(['uid' => $student->id(), 'activity_id' => (string) $activity->id()]);
    self::assertCount(3, $archives);
    $last = array_values($archives)[2];
    self::assertSame('deleted', $last->get('event_type')->value);
    self::assertSame(91.75, (float) $last->get('score_precise')->value);
  }

  /** Removes attempt history when its student account is deleted. */
  public function testAccountDeletionPurgesAcultaAttemptHistory(): void {
    [$student, , , , $answer] = $this->createAcultaAttempt();
    $answer->delete();
    self::assertCount(1, $this->archivesForAcultaAttempt($student, $answer));

    $student->delete();

    self::assertCount(0, $this->archivesForAcultaAttempt($student, $answer));
  }

  /** Removes ACULTA attempt history when its course is deleted. */
  public function testCourseDeletionPurgesAcultaAttemptHistory(): void {
    [, $course, , , $answer] = $this->createAcultaAttempt();
    $answer->delete();
    self::assertCount(1, $this->archivesForAcultaAttempt(NULL, $answer));

    $course->delete();

    self::assertCount(0, $this->archivesForAcultaAttempt(NULL, $answer));
  }

  /** Restricts ACULTA history by course and renders stored text safely. */
  public function testAcultaHistoryAccessIsCourseScopedAndOutputIsPlainText(): void {
    [$student, $course, , $activity, $answer] = $this->createAcultaAttempt(['answer' => '<script>alert(1)</script>Resposta']);
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
    $comment = Comment::create([
      'entity_type' => 'aculta_lms_answer',
      'entity_id' => $answer->id(),
      'field_name' => 'comments',
      'comment_type' => 'aculta_lms_answer_comment',
      'comment_body' => ['value' => 'Feedback <script>alert(2)</script>', 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
      'uid' => $student->id(),
    ]);
    $comment->save();
    $answer->set('state', 'graded')->set('score', 75)->save();
    $archive = $this->archivesForAcultaAttempt($student, $answer)[0];

    self::assertTrue($archive->access('view', $student));
    self::assertTrue($archive->access('view', $teacher));
    self::assertFalse($archive->access('view', $outsider));

    $session_account = $this->container->get('current_user');
    $session_account->setAccount($student);
    self::assertTrue($archive->access('view', $session_account), 'A session AccountProxy resolves to its active User entity.');
    $student->block()->save();
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_lms_attempt_archive')
      ->resetCache();
    self::assertFalse($archive->access('view', $session_account), 'A blocked student session cannot access archived answers.');
    $student->activate()->save();
    $teacher->block()->save();
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_lms_attempt_archive')
      ->resetCache();
    self::assertFalse($archive->access('view', $teacher), 'A blocked educator cannot access archived answers.');
    $teacher->activate()->save();
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_lms_attempt_archive')
      ->resetCache();

    $course->removeMember($student);
    // Access results are cached for the current request; force a fresh result
    // to model the next request after Group invalidates membership caches.
    $this->container->get('cache.group_memberships_chained')->deleteAll();
    $this->container->get('entity_type.manager')->getStorage('group_relationship')->resetCache();
    $this->container->get('entity_type.manager')->getStorage('group')->resetCache([$course->id()]);
    self::assertFalse((bool) $course->getMember($student), 'The Group membership fixture has been removed.');
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_lms_attempt_archive')
      ->resetCache();
    $direct_access = $this->container->get(\Drupal\aculta_lms\Hook\AttemptArchiveHooks::class)
      ->attemptArchiveAccess($archive, 'view', $student);
    self::assertTrue($direct_access->isForbidden(), sprintf(
      'Expected direct denial, got allowed=%s neutral=%s for administer lms=%s.',
      $direct_access->isAllowed() ? 'yes' : 'no',
      $direct_access->isNeutral() ? 'yes' : 'no',
      $student->hasPermission('administer lms') ? 'yes' : 'no',
    ));
    self::assertFalse($archive->access('view', $student), 'Removing the student membership immediately revokes archive access.');
    $course->addMember($student);
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_lms_attempt_archive')
      ->resetCache();

    $other_course = Group::create([
      'type' => 'lms_course',
      'label' => 'Outro curso',
      'uid' => $teacher->id(),
    ]);
    $other_course->save();
    $other_archive = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive')->create([
      'uid' => $student->id(),
      'group_id' => $other_course->id(),
      'course_status_id' => '',
      'lesson_status_id' => '',
      'lesson_id' => '0',
      'lesson_label' => 'Outra aula',
      'activity_id' => 'other-activity',
      'activity_label' => 'Outra atividade',
      'answer_uuid' => 'other-answer',
      'event_type' => 'deleted',
      'answer_data' => '{"answer":"outra resposta"}',
      'score' => 0,
      'max_score' => 100,
      'started' => 0,
      'finished' => 0,
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
    $this->container->get(\Drupal\aculta_lms\Hook\AttemptArchiveHooks::class)
      ->attemptArchiveView($build, $answer, $this->createMock(\Drupal\Core\Entity\Display\EntityViewDisplayInterface::class), 'full');
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
    self::assertContains('user', $build['previous_attempts']['#cache']['contexts']);
    self::assertContains('user.group_permissions', $build['previous_attempts']['#cache']['contexts']);
  }

  /**
   * Revalidates uploaded-answer access after membership and account changes.
   */
  public function testUploadedAnswerAccessTracksMembershipRevocationAndTeacherRole(): void {
    [$student, , , , $answer] = $this->createAttempt();
    $course = $answer->getLessonStatus()->getCourseStatus()->getCourse();
    $member_role = GroupRole::create([
      'id' => 'lms_course-upload_test_member',
      'label' => 'Upload test student',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => ['take course'],
    ]);
    $member_role->save();
    $teacher_role = GroupRole::create([
      'id' => 'lms_course-upload_test_teacher',
      'label' => 'Upload test teacher',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students'],
    ]);
    $teacher_role->save();

    $course->addMember($student);
    $other_student = $this->createUser();
    $course->addMember($other_student);
    $this->setCurrentUser($student);
    $access_check = $this->container->get('aculta_lms.uploaded_answer_access');
    $session_account = $this->container->get('current_user');
    self::assertTrue($access_check->access($answer, $session_account)->isAllowed(), 'An enrolled answer owner can download their file.');
    self::assertFalse($access_check->access($answer, $other_student)->isAllowed(), 'Another enrolled student cannot download a peer submission.');

    $student->block()->save();
    self::assertFalse($access_check->access($answer, $session_account)->isAllowed(), 'A blocked student session cannot download an uploaded answer.');
    $student->activate()->save();

    $course->removeMember($student);
    self::assertFalse($access_check->access($answer, $student)->isAllowed(), 'Revoking membership immediately blocks the answer owner.');

    $teacher = $this->createUser();
    $course->addMember($teacher, ['group_roles' => [$teacher_role->id()]]);
    $session_account->setAccount($teacher);
    self::assertTrue($access_check->access($answer, $session_account)->isAllowed(), 'An educator with grade permission retains access after student membership is revoked.');
    $teacher->block()->save();
    self::assertFalse($access_check->access($answer, $session_account)->isAllowed(), 'A blocked educator session cannot download an uploaded answer.');
  }

  /**
   * Denies a pending answer after its lesson status is deleted.
   */
  public function testUploadedAnswerAccessFailsClosedDuringQueuedStatusDeletion(): void {
    [$student, , $lesson_status, , $answer] = $this->createAttempt('Entrega pendente', 'file_upload');
    $answer_id = (int) $answer->id();
    $lesson_status->delete();

    $answer_storage = $this->container->get('entity_type.manager')->getStorage('lms_answer');
    $answer_storage->resetCache([$answer_id]);
    $answer = $answer_storage->load($answer_id);
    self::assertInstanceOf(AnswerInterface::class, $answer, 'LMS queues answer deletion after deleting its lesson status.');
    self::assertNull($answer->get('lesson_status')->entity, 'The answer reference is temporarily unresolved before the queue runs.');

    $this->setCurrentUser($student);
    $access = $this->container->get('aculta_lms.uploaded_answer_access')->access($answer, $student);
    self::assertFalse($access->isAllowed(), 'A missing course context never grants a private download.');
    self::assertContains('user', $access->getCacheContexts());
  }

  /**
   * Denies a pending answer while its course-status deletion is queued.
   */
  public function testUploadedAnswerAccessFailsClosedDuringQueuedCourseStatusDeletion(): void {
    [$student, $course_status, $lesson_status, , $answer] = $this->createAttempt('Entrega pendente', 'file_upload');
    $answer_id = (int) $answer->id();
    $course_status->delete();

    $answer_storage = $this->container->get('entity_type.manager')->getStorage('lms_answer');
    $answer_storage->resetCache([$answer_id]);
    $answer = $answer_storage->load($answer_id);
    self::assertInstanceOf(AnswerInterface::class, $answer, 'LMS queues dependent statuses and answers after deleting a course status.');
    self::assertNotNull($answer->get('lesson_status')->entity, 'The lesson status still exists until its queued deletion runs.');
    self::assertNull($lesson_status->get('course_status')->entity, 'The lesson status temporarily has no course status reference.');

    $this->setCurrentUser($student);
    $access = $this->container->get('aculta_lms.uploaded_answer_access')->access($answer, $student);
    self::assertFalse($access->isAllowed(), 'A removed course status never grants a private download during queue cleanup.');
    self::assertContains('user', $access->getCacheContexts());
  }

  /**
   * Streams an answer file only while course access remains current.
   */
  public function testPrivateUploadRouteStreamsOnlyWhileCourseAccessIsCurrent(): void {
    [$student, , , , $answer] = $this->createAttempt('Entrega de teste', 'file_upload');
    $course = $answer->getLessonStatus()->getCourseStatus()->getCourse();
    $member_role = GroupRole::create([
      'id' => 'lms_course-file_test_member',
      'label' => 'File test student',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => ['take course'],
    ]);
    $member_role->save();
    $teacher_role = GroupRole::create([
      'id' => 'lms_course-file_test_teacher',
      'label' => 'File test teacher',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students'],
    ]);
    $teacher_role->save();
    $course->addMember($student);
    $this->setCurrentUser($student);

    $upload_directory = 'private://lms_file_upload';
    self::assertTrue(
      $this->container->get('file_system')->prepareDirectory(
        $upload_directory,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
      ),
    );
    $file = $this->container->get('file.repository')->writeData(
      'private LMS submission content',
      'private://lms_file_upload/submission.txt',
      FileExists::Rename,
    );
    self::assertNotFalse($file);
    $file->setPermanent();
    $file->save();
    $answer->setData(['file_upload' => [$file->id()]])->save();
    $this->container->get('file.usage')->add($file, 'lms_file_upload', 'lms_answer', $answer->id());

    $route_name = 'aculta_lms.answer_file_download';
    $route = $this->container->get('router.route_provider')->getRouteByName($route_name);
    self::assertSame('/resposta/{lms_answer}/baixar-arquivo', $route->getPath());
    $access_manager = $this->container->get('access_manager');
    self::assertTrue($access_manager->checkNamedRoute($route_name, ['lms_answer' => $answer->id()], $student, TRUE)->isAllowed());

    $response = (new AnswerFileDownloadController($this->container->get('lms.training_manager')))->download($answer);
    ob_start();
    $response->sendContent();
    $body = ob_get_clean();
    self::assertSame('private LMS submission content', $body);
    self::assertStringContainsString('attachment; filename="submission.txt"', $response->headers->get('Content-Disposition'));

    $course->removeMember($student);
    self::assertFalse($access_manager->checkNamedRoute($route_name, ['lms_answer' => $answer->id()], $student, TRUE)->isAllowed());

    $teacher = $this->createUser();
    $course->addMember($teacher, ['group_roles' => [$teacher_role->id()]]);
    self::assertTrue($access_manager->checkNamedRoute($route_name, ['lms_answer' => $answer->id()], $teacher, TRUE)->isAllowed());
  }

  /**
   * Tests ACULTA image constraints run through Drupal Core file validation. */
  public function testSubmissionImageConstraintUsesCoreFileValidator(): void {
    $validPath = $this->siteDirectory . '/private/answer-valid.png';
    $invalidPath = $this->siteDirectory . '/private/answer-invalid.png';
    // PNG 1x1 RGBA decodificável pela GD: a política central exige dimensões
    // legíveis, não apenas um cabeçalho válido.
    file_put_contents($validPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQImWP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', TRUE));
    file_put_contents($invalidPath, 'not an image');
    try {
      $validator = $this->container->get('file.validator');
      $valid = File::create([
        'uid' => 1,
        'filename' => 'answer-valid.png',
        'uri' => $validPath,
        'filemime' => 'image/png',
        'filesize' => filesize($validPath),
      ]);
      $invalid = File::create([
        'uid' => 1,
        'filename' => 'answer-invalid.png',
        'uri' => $invalidPath,
        'filemime' => 'image/png',
        'filesize' => filesize($invalidPath),
      ]);

      self::assertCount(0, $validator->validate($valid, ['AcultaLmsSubmissionImage' => []]));
      self::assertCount(1, $validator->validate($invalid, ['AcultaLmsSubmissionImage' => []]));
    }
    finally {
      if (is_file($validPath)) {
        unlink($validPath);
      }
      if (is_file($invalidPath)) {
        unlink($invalidPath);
      }
    }
  }

  /**
   * Creates a real course, status, activity, lesson and answer fixture.
   *
   * @return array{0: \Drupal\user\UserInterface, 1: CourseStatus, 2: LessonStatus, 3: Activity, 4: Answer}
   *   The student and created LMS entities.
   */
  private function createAttempt(string $answer_text = 'Resposta original', string $plugin_id = 'no_answer'): array {
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $this->setCurrentUser($owner);
    ActivityType::create([
      'id' => 'archive_test',
      'name' => 'Teste de arquivo',
      'pluginId' => $plugin_id,
      'pluginConfiguration' => $plugin_id === 'file_upload' ? ['default_file_extensions' => 'txt'] : [],
    ])->save();
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

  /** Creates a course, lesson, activity and immutable ACULTA attempt. */
  private function createAcultaAttempt(array $payload = ['answer' => 'Resposta original']): array {
    $student = $this->createUser();
    $course_owner = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso de teste ACULTA',
      'uid' => $course_owner->id(),
      'status' => TRUE,
    ]);
    $course->save();
    $lesson = LessonEntity::create([
      'course_id' => (int) $course->id(),
      'title' => 'Aula de teste',
      'published' => TRUE,
      'weight' => 0,
    ]);
    $lesson->save();
    $activity = ActivityEntity::create([
      'lesson_id' => (int) $lesson->id(),
      'type' => 'free_text',
      'graded' => TRUE,
      'weight' => 0,
    ]);
    $activity->save();
    $answer = AnswerEntity::create([
      'activity_id' => (int) $activity->id(),
      'uid' => (int) $student->id(),
      'state' => 'submitted',
      'answer_data' => Json::encode((object) $payload),
    ]);
    $answer->save();
    return [$student, $course, $lesson, $activity, $answer];
  }

  /**
   * Loads history for one ACULTA activity and optionally one student.
   *
   * @return \Drupal\aculta_lms\Entity\AttemptArchive[]
   *   The matching snapshots.
   */
  private function archivesForAcultaAttempt(?UserInterface $student, AnswerEntity $answer): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_attempt_archive');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('activity_id', (string) $answer->get('activity_id')->value)
      ->sort('archived', 'ASC');
    if ($student !== NULL) {
      $query->condition('uid', (string) $student->id());
    }
    $ids = $query->execute();
    return array_values($storage->loadMultiple($ids));
  }

}
