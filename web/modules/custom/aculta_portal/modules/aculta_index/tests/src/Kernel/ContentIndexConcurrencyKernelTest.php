<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies lifecycle locks across independent MariaDB connections.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexConcurrencyKernelTest extends KernelTestBase {

  private const COMPETITOR_KEY = 'aculta_index_competitor';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'aculta_index',
    'aculta_index_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if ($this->container->get('database')->driver() !== 'mysql') {
      $this->markTestSkipped('This lock-contention contract requires MariaDB.');
    }
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    aculta_index_install();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    Database::removeConnection(self::COMPETITOR_KEY);
    parent::tearDown();
  }

  /**
   * Scheduling waits for promotion; queued changes then prevent rollback.
   */
  public function testPromotionSerializesSourceSchedulingAcrossConnections(): void {
    $database = $this->container->get('database');
    $manager = $this->container->get('aculta_index.generation_manager');
    $first = $this->validatedGeneration($manager);
    $manager->activate($first);
    $second = $this->validatedGeneration($manager);
    $competitor = $this->openCompetitor();
    $work = $this->workManager($competitor);
    $competingManager = $this->generationManager($competitor, $work);

    // Keep the production promotion's changes and locks uncommitted while a
    // separate database session attempts to schedule an entity change.
    $promotion = $database->startTransaction();
    try {
      $manager->activate($second);
      self::assertSame($first, $competingManager->getActiveGeneration(), 'Readers keep the old pointer until the promotion commits.');
      $this->assertLockWaitTimeout(static fn () => $work->schedule('test_sample_content', 'node', '41', 'en'));
      self::assertNull($work->getStatus('test_sample_content', 'node', '41', 'en'), 'A blocked scheduler must not partially persist work.');
    }
    finally {
      unset($promotion);
    }

    self::assertSame($second, $competingManager->getActiveGeneration());
    self::assertSame(1, $work->schedule('test_sample_content', 'node', '41', 'en'));
    try {
      $manager->activate($first);
      self::fail('Rollback must wait for the source change scheduled after promotion.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('waiting for source work to drain', $exception->getMessage());
    }
    self::assertSame($second, $competingManager->getActiveGeneration());

    $status = $work->getStatus('test_sample_content', 'node', '41', 'en');
    self::assertIsArray($status);
    $claim = $work->claim($status['work_id']);
    self::assertIsArray($claim);
    self::assertTrue($work->complete($claim['work_id'], $claim['sequence']));
    $manager->activate($first);
    self::assertSame($first, $competingManager->getActiveGeneration(), 'Rollback becomes possible after the current work sequence completes.');
  }

  /**
   * Another cleanup worker waits while the final deletion is uncommitted.
   */
  public function testCleanupSerializesFinalDeletionAcrossConnections(): void {
    $database = $this->container->get('database');
    $manager = $this->container->get('aculta_index.generation_manager');
    $generationId = $manager->start();
    $database->insert('aculta_index_entry')->fields([
      'generation_id' => $generationId,
      'provider_id' => 'test_sample_content',
      'entity_type' => 'node',
      'entity_id' => '41',
      'langcode' => 'en',
      'bundle' => 'sample_content',
    ])->execute();
    $manager->discardBuilding($generationId);
    $competitor = $this->openCompetitor();
    $competingManager = $this->generationManager($competitor, $this->workManager($competitor));

    $cleanup = $database->startTransaction();
    try {
      $result = $manager->discardPendingBatch(1);
      self::assertSame($generationId, $result['generation_id']);
      self::assertSame(1, $result['entries_removed']);
      self::assertTrue($result['complete']);
      $this->assertLockWaitTimeout(static fn () => $competingManager->discardPendingBatch(1));
    }
    finally {
      unset($cleanup);
    }

    $next = $competingManager->discardPendingBatch(1);
    self::assertNull($next['generation_id'], 'The competing worker finds no pending generation after the final deletion commits.');
    self::assertSame(0, $next['entries_removed']);
    self::assertTrue($next['complete']);
    self::assertSame(0, (int) $competitor->select('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->countQuery()->execute()->fetchField());
  }

  /**
   * Independent PHP processes cannot lose source sequences or double-claim.
   */
  public function testConcurrentPhpProcessesSerializeScheduleAndClaim(): void {
    if (!function_exists('proc_open')) {
      self::markTestSkipped('This process-level concurrency test requires proc_open.');
    }

    $providerId = 'process_stress';
    $entityTypeId = 'node';
    $entityId = 'process-stress-1';
    $langcode = 'pt-br';
    $workManager = $this->container->get('aculta_index.work_manager');
    self::assertSame(1, $workManager->schedule($providerId, $entityTypeId, $entityId, $langcode));
    $initial = $workManager->getStatus($providerId, $entityTypeId, $entityId, $langcode);
    self::assertIsArray($initial);

    $processCount = 4;
    $iterationsPerProcess = 25;
    $producerResults = $this->runProcessWorkers(array_fill(0, $processCount, [
      'action' => 'schedule',
      'iterations' => $iterationsPerProcess,
      'work_id' => 0,
    ]));
    $sequences = [];
    foreach ($producerResults as $result) {
      self::assertIsArray($result['sequences'] ?? NULL);
      array_push($sequences, ...$result['sequences']);
    }
    sort($sequences, SORT_NUMERIC);
    $expectedSequences = range(2, 1 + $processCount * $iterationsPerProcess);
    self::assertSame($expectedSequences, $sequences, 'Concurrent event producers must reserve every sequence exactly once.');

    $queued = $workManager->getStatus($providerId, $entityTypeId, $entityId, $langcode);
    self::assertIsArray($queued);
    self::assertSame(101, $queued['sequence']);
    self::assertSame('queued', $queued['status']);

    $claimResults = $this->runProcessWorkers(array_fill(0, $processCount, [
      'action' => 'claim',
      'iterations' => 0,
      'work_id' => $queued['work_id'],
    ]));
    $claims = array_values(array_filter(array_column($claimResults, 'claim'), 'is_array'));
    self::assertCount(1, $claims, 'Only one independent process may claim a queued sequence.');
    self::assertSame(101, $claims[0]['sequence']);

    self::assertSame(102, $workManager->schedule($providerId, $entityTypeId, $entityId, $langcode));
    self::assertFalse($workManager->complete($queued['work_id'], $claims[0]['sequence']), 'A late completion cannot mark a newer event done.');
    $latest = $workManager->getStatus($providerId, $entityTypeId, $entityId, $langcode);
    self::assertIsArray($latest);
    self::assertSame(102, $latest['sequence']);
    self::assertSame('queued', $latest['status']);
  }

  /**
   * Runs production work-manager operations in PHP processes on this DB prefix.
   *
   * @param list<array{action: string, iterations: int, work_id: int}> $workers
   *   Concurrent worker specifications.
   *
   * @return list<array<string, mixed>>
   *   Decoded JSON worker results.
   */
  private function runProcessWorkers(array $workers): array {
    $testDirectory = dirname(__DIR__, 2);
    $workerScript = $testDirectory . '/fixtures/content-index-process-worker.php';
    $autoload = DRUPAL_ROOT . '/../vendor/autoload.php';
    $connectionOptions = $this->container->get('database')->getConnectionOptions();
    $configurationPath = tempnam(sys_get_temp_dir(), 'aculta-index-process-db-');
    $barrierPath = tempnam(sys_get_temp_dir(), 'aculta-index-process-barrier-');
    if ($configurationPath === FALSE || $barrierPath === FALSE) {
      if (is_string($configurationPath)) {
        @unlink($configurationPath);
      }
      if (is_string($barrierPath)) {
        @unlink($barrierPath);
      }
      throw new \RuntimeException('Could not allocate temporary process-test files.');
    }
    $processes = [];
    try {
      chmod($configurationPath, 0600);
      unlink($barrierPath);
      $configuration = json_encode(['connection' => $connectionOptions], JSON_THROW_ON_ERROR);
      if (file_put_contents($configurationPath, $configuration, LOCK_EX) !== strlen($configuration)) {
        throw new \RuntimeException('Could not write temporary process-test database options.');
      }

      foreach ($workers as $worker) {
        $descriptors = [
          0 => ['pipe', 'r'],
          1 => ['pipe', 'w'],
          2 => ['pipe', 'w'],
        ];
        $process = proc_open([
          PHP_BINARY,
          $workerScript,
          $autoload,
          $configurationPath,
          $barrierPath,
          $worker['action'],
          (string) $worker['iterations'],
          (string) $worker['work_id'],
        ], $descriptors, $pipes, DRUPAL_ROOT);
        if (!is_resource($process)) {
          throw new \RuntimeException('Could not start a Content Index process worker.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], FALSE);
        stream_set_blocking($pipes[2], FALSE);
        $processes[] = [
          'process' => $process,
          'pipes' => $pipes,
          'stdout' => '',
          'stderr' => '',
          'exit_code' => NULL,
          'finished' => FALSE,
        ];
      }

      if (file_put_contents($barrierPath, 'start', LOCK_EX) === FALSE) {
        throw new \RuntimeException('Could not release the process-test barrier.');
      }

      $deadline = microtime(TRUE) + 120;
      $runningProcesses = count($processes);
      $openPipes = count($processes) * 2;
      while ($runningProcesses > 0 || $openPipes > 0) {
        if (microtime(TRUE) >= $deadline) {
          throw new \RuntimeException('Timed out waiting for Content Index process workers.');
        }

        $read = [];
        $pipeOwners = [];
        foreach ($processes as $processIndex => $workerProcess) {
          foreach ([1, 2] as $pipeIndex) {
            $pipe = $workerProcess['pipes'][$pipeIndex];
            if (is_resource($pipe)) {
              $read[] = $pipe;
              $pipeOwners[(int) $pipe] = [$processIndex, $pipeIndex];
            }
          }
        }
        if ($read !== []) {
          $write = NULL;
          $except = NULL;
          $selected = stream_select($read, $write, $except, 0, 200_000);
          if ($selected === FALSE) {
            throw new \RuntimeException('Could not collect Content Index process-worker output.');
          }
          foreach ($read as $pipe) {
            [$processIndex, $pipeIndex] = $pipeOwners[(int) $pipe];
            $stream = $pipeIndex === 1 ? 'stdout' : 'stderr';
            $processes[$processIndex][$stream] .= (string) fread($pipe, 8192);
            if (feof($pipe)) {
              fclose($pipe);
              $processes[$processIndex]['pipes'][$pipeIndex] = NULL;
              $openPipes--;
            }
          }
        }

        foreach ($processes as $processIndex => $workerProcess) {
          if ($workerProcess['finished']) {
            continue;
          }
          $status = proc_get_status($workerProcess['process']);
          if (!$status['running']) {
            $processes[$processIndex]['finished'] = TRUE;
            $processes[$processIndex]['exit_code'] = $status['exitcode'];
            $runningProcesses--;
          }
        }
      }

      $results = [];
      foreach ($processes as $processIndex => $workerProcess) {
        foreach ([1, 2] as $pipeIndex) {
          if (is_resource($workerProcess['pipes'][$pipeIndex])) {
            fclose($workerProcess['pipes'][$pipeIndex]);
          }
        }
        $closeCode = proc_close($workerProcess['process']);
        $processes[$processIndex]['process'] = NULL;
        $exitCode = is_int($workerProcess['exit_code']) && $workerProcess['exit_code'] >= 0
          ? $workerProcess['exit_code']
          : $closeCode;
        self::assertSame(0, $exitCode, sprintf('Process worker failed: %s', trim($workerProcess['stderr'])));
        $decoded = json_decode(trim($workerProcess['stdout']), TRUE, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $results[] = $decoded;
      }
      return $results;
    }
    finally {
      foreach ($processes as $workerProcess) {
        if (is_resource($workerProcess['process'])) {
          proc_terminate($workerProcess['process']);
          foreach ([1, 2] as $pipeIndex) {
            if (is_resource($workerProcess['pipes'][$pipeIndex])) {
              fclose($workerProcess['pipes'][$pipeIndex]);
            }
          }
          proc_close($workerProcess['process']);
        }
      }
      @unlink($configurationPath);
      @unlink($barrierPath);
    }
  }

  /**
   * Opens a second physical session with the Kernel's isolated table prefix.
   */
  private function openCompetitor(): Connection {
    $database = $this->container->get('database');
    Database::addConnectionInfo(self::COMPETITOR_KEY, 'default', $database->getConnectionOptions());
    $competitor = Database::getConnection('default', self::COMPETITOR_KEY);
    self::assertNotSame(
      (int) $database->query('SELECT CONNECTION_ID()')->fetchField(),
      (int) $competitor->query('SELECT CONNECTION_ID()')->fetchField(),
    );
    $competitor->query('SET SESSION innodb_lock_wait_timeout = 1');
    return $competitor;
  }

  /**
   * Builds the production work manager against a specific database session.
   */
  private function workManager(Connection $database): ContentIndexWorkManager {
    return new ContentIndexWorkManager(
      $database,
      $this->container->get('queue'),
      $this->container->get('datetime.time'),
      $this->container->get('aculta_index.provider_registry'),
      $this->container->get('logger.channel.aculta_index'),
    );
  }

  /**
   * Builds the production generation manager against the same session.
   */
  private function generationManager(Connection $database, ContentIndexWorkManager $work): ContentIndexGenerationManager {
    return new ContentIndexGenerationManager(
      $database,
      $this->container->get('uuid'),
      $this->container->get('datetime.time'),
      $work,
      $this->container->get('aculta_index.provider_registry'),
    );
  }

  /**
   * Prepares an empty generation with completed backfill for lock tests.
   */
  private function validatedGeneration(ContentIndexGenerationManager $manager): string {
    $id = $manager->start();
    $manager->markBackfillCompleted($id, 0);
    $manager->markValidated($id);
    return $id;
  }

  /**
   * Confirms that a competing statement waits for the held InnoDB row lock.
   */
  private function assertLockWaitTimeout(callable $operation): void {
    try {
      $operation();
      self::fail('The competing operation must wait for the uncommitted lifecycle change.');
    }
    catch (DatabaseExceptionWrapper $exception) {
      $cause = $exception->getPrevious();
      self::assertInstanceOf(\PDOException::class, $cause);
      self::assertSame(1205, (int) ($cause->errorInfo[1] ?? 0), 'Only an InnoDB lock wait timeout proves the expected contention.');
    }
  }

}
