<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Performance;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Opt-in benchmark for provider-backed rebuild and incremental audit.
 *
 * @group aculta_index
 * @group performance
 */
#[Group('aculta_index')]
#[Group('performance')]
#[RunTestsInSeparateProcesses]
final class ContentIndexMaintenanceBenchmarkTest extends KernelTestBase {

  /**
   * Source nodes used by the synthetic provider.
   */
  private const DEFAULT_SOURCE_COUNT = 10_000;

  /**
   * Approved synthetic fixture scale for this process.
   */
  private int $sourceCount = self::DEFAULT_SOURCE_COUNT;

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
    $databaseUrl = getenv('SIMPLETEST_DB') ?: '';
    $databaseParts = parse_url($databaseUrl);
    if (!is_array($databaseParts)
      || ($databaseParts['host'] ?? '') !== 'localhost'
      || trim((string) ($databaseParts['path'] ?? ''), '/') !== 'aculta_simpletest') {
      throw new \RuntimeException('The ACULTA Content Index maintenance benchmark requires the dedicated localhost aculta_simpletest database.');
    }

    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node']);
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
    NodeType::create(['type' => 'sample_content', 'name' => 'Synthetic benchmark content'])->save();
  }

  /**
   * Measures provider backfill and two-direction audit over real Node storage.
   *
   * Set ACULTA_INDEX_MAINTENANCE_BENCHMARK=1 to run synthetic sources.
   * ACULTA_INDEX_MAINTENANCE_BENCHMARK_SIZE accepts 10000, 100000 or 1000000.
   */
  public function testRebuildAndIncrementalAuditAtConfiguredScale(): void {
    if (getenv('ACULTA_INDEX_MAINTENANCE_BENCHMARK') !== '1') {
      self::markTestSkipped('Set ACULTA_INDEX_MAINTENANCE_BENCHMARK=1 to run the rebuild and audit benchmark.');
    }
    $requestedSize = getenv('ACULTA_INDEX_MAINTENANCE_BENCHMARK_SIZE');
    if ($requestedSize !== FALSE && $requestedSize !== '') {
      if (!in_array($requestedSize, ['10000', '100000', '1000000'], TRUE)) {
        throw new \InvalidArgumentException('Maintenance benchmark size must be one of 10000, 100000 or 1000000.');
      }
      $this->sourceCount = (int) $requestedSize;
    }

    $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $outputFile = getenv('ACULTA_INDEX_MAINTENANCE_BENCHMARK_OUTPUT')
      ?: $temporaryDirectory . 'aculta-index-maintenance-' . $this->sourceCount . '-' . getmypid() . '.json';
    if (!str_starts_with($outputFile, $temporaryDirectory)) {
      throw new \RuntimeException('The maintenance benchmark result must be written inside sys_get_temp_dir().');
    }

    $sourceSetupStarted = hrtime(TRUE);
    // Source creation is outside the measured rebuild. Temporarily hide the
    // providers so entity hooks do not persist one incremental work row per
    // synthetic source; the real registry is restored before rebuild/audit.
    $providerRegistry = $this->container->get('aculta_index.provider_registry');
    $this->container->set('aculta_index.provider_registry', new ContentIndexProviderRegistry());
    try {
      $this->createSyntheticSources();
    }
    finally {
      $this->container->set('aculta_index.provider_registry', $providerRegistry);
    }
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_work', 'work')
      ->condition('provider_id', 'test_sample_content')
      ->countQuery()
      ->execute()
      ->fetchField(), 'Synthetic source setup must not persist incremental work rows.');
    $sourceSetupMilliseconds = (hrtime(TRUE) - $sourceSetupStarted) / 1_000_000;
    $sourceSetupMemoryBytes = memory_get_usage(TRUE);
    $sourceSetupPeakMemoryBytes = memory_get_peak_usage(TRUE);
    memory_reset_peak_usage();
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $rebuildBatchTimes = [];
    $rebuildStarted = hrtime(TRUE);
    $rebuildBatchCount = 0;
    do {
      $batchStarted = hrtime(TRUE);
      $batch = $rebuilder->processNextBatch($generationId, 500);
      $rebuildBatchTimes[] = (hrtime(TRUE) - $batchStarted) / 1_000_000;
      $rebuildBatchCount++;
      if ($rebuildBatchCount > (int) ceil($this->sourceCount / 500) + 2) {
        throw new \RuntimeException('The benchmark rebuild did not reach its source checkpoint within the expected batches.');
      }
    } while (!$batch['complete']);
    $rebuildMilliseconds = (hrtime(TRUE) - $rebuildStarted) / 1_000_000;
    $rebuildPeakMemoryBytes = memory_get_peak_usage(TRUE);
    $projectedCount = (int) $this->container->get('database')->select('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->countQuery()
      ->execute()
      ->fetchField();
    self::assertSame($this->sourceCount, $projectedCount);

    // A direct active-pointer switch isolates event delivery; activation is
    // covered by ContentIndexRebuilderKernelTest.
    $database = $this->container->get('database');
    $database->update('aculta_index_generation')
      ->fields(['status' => 'active'])
      ->condition('generation_id', $generationId)
      ->execute();
    $database->update('aculta_index_state')
      ->fields(['active_generation' => $generationId, 'revision' => 1])
      ->condition('state_id', 1)
      ->execute();

    $integrityChecker = $this->container->get('aculta_index.integrity_checker');
    $integrityChecker->startAudit();
    $auditBatchTimes = [];
    $auditStarted = hrtime(TRUE);
    memory_reset_peak_usage();
    $auditBatchCount = 0;
    do {
      $batchStarted = hrtime(TRUE);
      $audit = $integrityChecker->processAuditBatch(200);
      $auditBatchTimes[] = (hrtime(TRUE) - $batchStarted) / 1_000_000;
      $auditBatchCount++;
      // The audit has separate catalog-to-source and source-to-catalog
      // checkpoint passes, each bounded by the configured batch size.
      if ($auditBatchCount > (int) ceil($this->sourceCount / 200) * 2 + 20) {
        throw new \RuntimeException('The benchmark audit did not finish within the bounded checkpoint allowance.');
      }
    } while ($audit['status'] === 'running');
    $auditMilliseconds = (hrtime(TRUE) - $auditStarted) / 1_000_000;
    $auditPeakMemoryBytes = memory_get_peak_usage(TRUE);
    self::assertSame('complete', $audit['status']);
    self::assertSame(0, (int) $audit['missing_source_count']);
    self::assertSame(0, (int) $audit['missing_catalog_entry_count']);
    self::assertSame(0, (int) $audit['stale_projection_count']);

    sort($rebuildBatchTimes, SORT_NUMERIC);
    sort($auditBatchTimes, SORT_NUMERIC);
    $payload = [
      'benchmark' => 'provider_backfill_and_incremental_audit',
      'date' => gmdate('Y-m-d'),
      'drupal' => \Drupal::VERSION,
      'php' => PHP_VERSION,
      'database' => $database->version(),
      'source_count' => $this->sourceCount,
      'source_setup_elapsed_ms' => round($sourceSetupMilliseconds, 3),
      'source_setup_memory_bytes' => $sourceSetupMemoryBytes,
      'source_setup_peak_memory_bytes' => $sourceSetupPeakMemoryBytes,
      'provider_id' => 'test_sample_content',
      'rebuild_batch_limit' => 500,
      'writer_transaction_batch_size' => 500,
      'rebuild_batch_count' => $rebuildBatchCount,
      'rebuild_projected_count' => $projectedCount,
      'rebuild_elapsed_ms' => round($rebuildMilliseconds, 3),
      'rebuild_rows_per_second' => (int) round($this->sourceCount / max($rebuildMilliseconds / 1000, 0.000001)),
      'rebuild_batch_p50_ms' => round($this->percentile($rebuildBatchTimes, 0.50), 3),
      'rebuild_batch_p95_ms' => round($this->percentile($rebuildBatchTimes, 0.95), 3),
      'rebuild_batch_max_ms' => round(max($rebuildBatchTimes), 3),
      'rebuild_peak_memory_bytes' => $rebuildPeakMemoryBytes,
      'audit_batch_limit' => 200,
      'audit_batch_count' => $auditBatchCount,
      'audit_status' => $audit['status'],
      'audit_elapsed_ms' => round($auditMilliseconds, 3),
      'audit_batches_per_second' => (int) round($auditBatchCount / max($auditMilliseconds / 1000, 0.000001)),
      'audit_batch_p50_ms' => round($this->percentile($auditBatchTimes, 0.50), 3),
      'audit_batch_p95_ms' => round($this->percentile($auditBatchTimes, 0.95), 3),
      'audit_batch_max_ms' => round(max($auditBatchTimes), 3),
      'audit_peak_memory_bytes' => $auditPeakMemoryBytes,
      'missing_source_count' => (int) $audit['missing_source_count'],
      'missing_catalog_entry_count' => (int) $audit['missing_catalog_entry_count'],
      'stale_projection_count' => (int) $audit['stale_projection_count'],
      'peak_memory_bytes' => max($sourceSetupPeakMemoryBytes, $rebuildPeakMemoryBytes, $auditPeakMemoryBytes),
      'scope' => 'Real Drupal Node storage, test provider, production enumerator, writer, rebuild and incremental integrity audit. Source-entity creation and activation are excluded from rebuild/audit timing; incremental work is suppressed during fixture creation.',
      'slo' => FALSE,
    ];
    if (file_put_contents($outputFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX) === FALSE) {
      throw new \RuntimeException('Could not write the Content Index maintenance benchmark result.');
    }
    self::assertFileExists($outputFile);
  }

  /**
   * Creates the synthetic source entities outside the measured intervals.
   */
  private function createSyntheticSources(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    for ($start = 0; $start < $this->sourceCount; $start += 100) {
      for ($index = $start; $index < min($start + 100, $this->sourceCount); $index++) {
        $storage->create([
          'type' => 'sample_content',
          'title' => 'Synthetic maintenance source ' . $index,
          'status' => 1,
          'created' => 1_700_000_000 + $index,
          'changed' => 1_700_000_000 + $index,
        ])->save();
      }
    }
  }

  /**
   * Calculates a nearest-rank percentile.
   *
   * @param list<float> $samples
   *   Sorted elapsed-time samples in milliseconds.
   * @param float $fraction
   *   Percentile fraction between zero and one.
   */
  private function percentile(array $samples, float $fraction): float {
    return $samples[max(0, (int) ceil(count($samples) * $fraction) - 1)];
  }

}
