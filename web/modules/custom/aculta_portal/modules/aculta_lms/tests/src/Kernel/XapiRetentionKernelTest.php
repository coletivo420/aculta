<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_lms\Hook\XapiRetentionCronHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Exercises xAPI retention against real Drupal entity storage in MariaDB. */
#[Group('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class XapiRetentionKernelTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  protected static $modules = [
    'system', 'user', 'file', 'image', 'media', 'options', 'text', 'entity_print', 'domain', 'views',
    'entity', 'flexible_permissions', 'group', 'lms', 'key', 'entity_usage', 'aculta_image', 'aculta_file_uploader',
  ];

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('group');
  }

  public function testPurgesOnlyExpiredStatementsAndIsMonthlyIdempotent(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('aculta_lms_xapi_statement');
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_xapi_statement');
    $old = $storage->create([
      'statement_id' => '8af7f380-1b9d-4e62-94d7-3ed573ee8001',
      'uid' => 42,
      'registration' => 'reg-old',
      'lesson_status_id' => 17,
      'verb_id' => 'https://example.org/completed',
      'object_id' => 'https://example.org/activity',
      'payload' => '{}',
      'statement_timestamp' => 1,
      'received' => \Drupal::time()->getRequestTime() - (8 * 30 * 86400),
    ]);
    $old->save();
    $recent = $storage->create([
      'statement_id' => '8af7f380-1b9d-4e62-94d7-3ed573ee8002',
      'uid' => 42,
      'registration' => 'reg-new',
      'lesson_status_id' => 17,
      'verb_id' => 'https://example.org/completed',
      'object_id' => 'https://example.org/activity',
      'payload' => '{}',
      'statement_timestamp' => 1,
      'received' => \Drupal::time()->getRequestTime(),
    ]);
    $recent->save();

    $purger = $this->container->get('aculta_lms.xapi_statement_retention');
    self::assertSame(1, $purger->purge(6));
    self::assertNull($storage->load($old->id()));
    self::assertNotNull($storage->load($recent->id()));
    self::assertSame(0, $purger->purge(6));
  }

  public function testCronHookUsesConfiguredRetentionAndCompletesTheMonth(): void {
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->installEntitySchema('aculta_lms_xapi_statement');
    $this->config('aculta_lms.xapi')->set('retention_months', 6)->save();
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_xapi_statement');
    $old = $storage->create([
      'statement_id' => '8af7f380-1b9d-4e62-94d7-3ed573ee8003',
      'uid' => 42,
      'registration' => 'reg-cron-old',
      'lesson_status_id' => 17,
      'verb_id' => 'https://example.org/completed',
      'object_id' => 'https://example.org/activity',
      'payload' => '{}',
      'statement_timestamp' => 1,
      'received' => \Drupal::time()->getRequestTime() - (8 * 30 * 86400),
    ]);
    $old->save();
    $recent = $storage->create([
      'statement_id' => '8af7f380-1b9d-4e62-94d7-3ed573ee8004',
      'uid' => 42,
      'registration' => 'reg-cron-recent',
      'lesson_status_id' => 17,
      'verb_id' => 'https://example.org/completed',
      'object_id' => 'https://example.org/activity',
      'payload' => '{}',
      'statement_timestamp' => 1,
      'received' => \Drupal::time()->getRequestTime(),
    ]);
    $recent->save();

    $cron = $this->container->get(XapiRetentionCronHooks::class);
    $cron->cron();

    self::assertNull($storage->load($old->id()));
    self::assertNotNull($storage->load($recent->id()));
    self::assertSame(gmdate('Y-m'), $this->container->get('state')->get('aculta_lms.xapi.last_completed_retention_month'));
    $cron->cron();
    self::assertNotNull($storage->load($recent->id()));
  }

}
