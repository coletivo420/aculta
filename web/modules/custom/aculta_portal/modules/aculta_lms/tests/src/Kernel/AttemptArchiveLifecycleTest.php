<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\aculta_lms\Hook\AttemptArchiveHooks;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\file\Entity\File;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Exercises ACULTA attempt capture, access and Comment lifecycle. */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AttemptArchiveLifecycleTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

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
    'editor',
    'ckeditor5',
    'comment',
    'aculta_lms',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
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
    foreach ([
      'group.type.lms_course',
      'group.relationship_type.lms_course-group_membership',
      'field.storage.group_relationship.group_roles',
      'field.field.group_relationship.lms_course-group_membership.group_roles',
    ] as $config_id) {
      $path = DRUPAL_ROOT . '/../config/sync/' . $config_id . '.yml';
      $data = Yaml::decode(file_get_contents($path));
      // Group 4 no longer defines creator_wizard in its GroupType schema.
      unset($data['creator_wizard']);
      $this->container->get('config.factory')->getEditable($config_id)
        ->setData($data)
        ->save();
    }
    $this->container->get('config.factory')->getEditable('group.type.lms_course')
      ->set('creator_membership', FALSE)
      ->set('creator_roles', [])
      ->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('group_relation_type.manager')->clearCachedPluginMaps();
    $this->installConfig(['aculta_lms']);
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
    $this->installEntitySchema('aculta_lms_activity');
    $this->installEntitySchema('aculta_lms_lesson');
    $this->installEntitySchema('aculta_lms_answer');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('aculta_lms_attempt_archive');
    $this->installConfig(['group']);
  }

  /**
   * Archives ACULTA attempts when grading changes or the attempt is deleted. */
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

  /**
   * Removes attempt history when its student account is deleted. */
  public function testAccountDeletionPurgesAcultaAttemptHistory(): void {
    [$student, , , , $answer] = $this->createAcultaAttempt();
    $answer->delete();
    self::assertCount(1, $this->archivesForAcultaAttempt($student, $answer));

    $student->delete();

    self::assertCount(0, $this->archivesForAcultaAttempt($student, $answer));
  }

  /**
   * Removes ACULTA attempt history when its course is deleted. */
  public function testCourseDeletionPurgesAcultaAttemptHistory(): void {
    [, $course, , , $answer] = $this->createAcultaAttempt();
    $answer->delete();
    self::assertCount(1, $this->archivesForAcultaAttempt(NULL, $answer));

    $course->delete();

    self::assertCount(0, $this->archivesForAcultaAttempt(NULL, $answer));
  }

  /**
   * Restricts ACULTA history by course and renders stored text safely. */
  public function testAcultaHistoryAccessIsCourseScopedAndOutputIsPlainText(): void {
    [$student, $course, , , $answer] = $this->createAcultaAttempt(['answer' => '<script>alert(1)</script>Resposta']);
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
    $direct_access = $this->container->get(AttemptArchiveHooks::class)
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
    $this->container->get(AttemptArchiveHooks::class)
      ->attemptArchiveView($build, $answer, $this->createMock(EntityViewDisplayInterface::class), 'full');
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
   * Creates a course, lesson, activity and immutable ACULTA attempt. */
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
