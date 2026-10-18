<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\UserSession;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Verifies optional installation and DI registration without module-owned data. */
#[Group('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AcultaLmsInstallTest extends KernelTestBase {

  protected static $modules = ['system', 'user'];

  public function testInstallRegistersOnlyTheOptionalIntegrationService(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_lms'], FALSE));
    $container = \Drupal::getContainer();
    $this->assertTrue($container->get('module_handler')->moduleExists('aculta_lms'));
    $this->assertInstanceOf(
      \Drupal\aculta_lms\Event\CourseCompletionPublisherInterface::class,
      $container->get('aculta_lms.course_completion_publisher'),
    );
    $this->assertFalse($container->get('config.storage')->exists('aculta_lms.settings'));
    $this->assertFalse($container->get('module_handler')->hasImplementations('install', ['aculta_lms']));
    $this->assertFalse($container->get('module_handler')->hasImplementations('schema', ['aculta_lms']));
  }

  public function testAttemptArchivePersistsIsReadOnlyAndPurgesWithAccount(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('aculta_lms_attempt_archive');
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

    $administrator = new UserSession(['uid' => 1, 'permissions' => ['administer lms']]);
    $this->assertTrue($archive->access('view', $administrator));
    $this->assertFalse($archive->access('update', $administrator));
    $this->assertSame('{"answer":"texto anterior"}', $storage->load($archive->id())->get('answer_data')->value);

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $this->container->get('aculta_lms.attempt_archive')->purgeByUser($account);
    $this->assertNull($storage->load($archive->id()));
  }

}
