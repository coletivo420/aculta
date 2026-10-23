<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Performance;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Opt-in candidate-query benchmark against synthetic catalog rows.
 *
 * @group aculta_index
 * @group performance
 */
#[Group('aculta_index')]
#[Group('performance')]
#[RunTestsInSeparateProcesses]
final class ContentIndexCatalogBenchmarkTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'entity_usage',
    'media_duplicates',
    'aculta_image',
    'aculta_index',
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
      throw new \RuntimeException('The ACULTA Content Index benchmark requires the dedicated localhost aculta_simpletest database.');
    }
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installConfig(['user', 'system']);
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
   * Measures cold and warm candidate pages at the approved catalog sizes.
   *
   * Set ACULTA_INDEX_BENCHMARK=1 to run the deliberately expensive fixture.
   */
  public function testCandidateQueryAtProgressiveCatalogSizes(): void {
    if (getenv('ACULTA_INDEX_BENCHMARK') !== '1') {
      self::markTestSkipped('Set ACULTA_INDEX_BENCHMARK=1 to run the 10k/100k/1M catalog benchmark.');
    }

    $account = $this->setUpCurrentUser([], ['access content']);
    $database = $this->container->get('database');
    $query = $this->container->get('aculta_index.query');
    $sizes = [10_000, 100_000, 1_000_000];
    $sizeOverride = getenv('ACULTA_INDEX_BENCHMARK_SIZES');
    if ($sizeOverride !== FALSE && $sizeOverride !== '') {
      $sizes = array_map('intval', explode(',', $sizeOverride));
      if (array_diff($sizes, [10_000, 100_000, 1_000_000]) !== []) {
        throw new \InvalidArgumentException('Benchmark sizes may only be 10000, 100000, or 1000000.');
      }
    }
    $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $outputFile = getenv('ACULTA_INDEX_BENCHMARK_OUTPUT') ?: $temporaryDirectory . 'aculta-index-benchmark-' . getmypid() . '.jsonl';
    if (!str_starts_with($outputFile, $temporaryDirectory)) {
      throw new \InvalidArgumentException('Benchmark results must be written inside the system temporary directory.');
    }
    if (file_put_contents($outputFile, '', LOCK_EX) === FALSE) {
      throw new \RuntimeException('Could not initialize the temporary benchmark results file.');
    }
    $batchSize = 500;

    foreach ($sizes as $size) {
      $generationId = $this->activateSyntheticGeneration();
      $insertStarted = hrtime(TRUE);
      for ($offset = 0; $offset < $size; $offset += $batchSize) {
        $insert = $database->insert('aculta_index_entry')
          ->fields([
            'generation_id',
            'provider_id',
            'provider_version',
            'entity_type',
            'entity_id',
            'langcode',
            'bundle',
            'uuid',
            'label',
            'domain_id',
            'published',
            'created',
            'changed',
            'published_at',
            'projection_hash',
            'source_sequence',
            'updated',
          ]);
        $end = min($offset + $batchSize, $size);
        for ($index = $offset; $index < $end; $index++) {
          $entityId = (string) ($index + 1);
          $domainId = ['main', 'forum', 'wiki', 'courses'][$index % 4];
          $published = $index % 10 === 0 ? 0 : 1;
          $insert->values([
            $generationId,
            'image',
            1,
            'media',
            $entityId,
            'en',
            'image',
            hash('sha256', 'aculta-index-benchmark:' . $entityId),
            'Synthetic image ' . $entityId,
            $domainId,
            $published,
            1_700_000_000 + $index,
            1_700_000_000 + $index,
            1_700_000_000 + $index,
            hash('sha256', $entityId),
            0,
            1_700_000_000,
          ]);
        }
        $insert->execute();
      }
      $insertMilliseconds = (hrtime(TRUE) - $insertStarted) / 1_000_000;

      $filters = [
        'entity_type' => 'media',
        'bundle' => 'image',
        'domain_id' => 'main',
        'published' => TRUE,
      ];
      $candidateCache = $this->container->get('aculta_index.candidate_cache');
      $coldMilliseconds = [];
      $candidateSamples = 30;
      for ($repeat = 0; $repeat < $candidateSamples; $repeat++) {
        $candidateCache->invalidateGeneration($generationId);
        $started = hrtime(TRUE);
        $coldPage = $query->query($filters, $account, NULL, 100);
        $coldMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
        self::assertNotNull($coldPage->nextCursor, 'The first candidate page has a keyset continuation.');
      }
      $warmMilliseconds = [];
      for ($repeat = 0; $repeat < $candidateSamples; $repeat++) {
        $started = hrtime(TRUE);
        $warmPage = $query->query($filters, $account, NULL, 100);
        $warmMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
        self::assertNotNull($warmPage->nextCursor);
      }
      sort($warmMilliseconds, SORT_NUMERIC);
      sort($coldMilliseconds, SORT_NUMERIC);

      // Compare one plausible filter-first index without changing the module
      // schema. The Kernel table is test-prefixed and discarded after the run.
      $schema = $database->schema();
      $baselineWriter = $this->measureWriterSample(250);
      $database->query('ANALYZE TABLE {aculta_index_entry}')->fetchAll();
      $baselineEntryBytes = $this->tableStorageBytes('aculta_index_entry');
      $indexBuildStarted = hrtime(TRUE);
      $schema->addIndex(
        'aculta_index_entry',
        'benchmark_filter_entry',
        ['generation_id', 'domain_id', 'published', 'entry_id'],
        aculta_index_schema()['aculta_index_entry'],
      );
      $indexBuildMilliseconds = (hrtime(TRUE) - $indexBuildStarted) / 1_000_000;
      $database->query('ANALYZE TABLE {aculta_index_entry}')->fetchAll();
      $alternativeEntryBytes = $this->tableStorageBytes('aculta_index_entry');
      $alternativeWriter = $this->measureWriterSample(250);
      $alternativeColdMilliseconds = [];
      for ($repeat = 0; $repeat < $candidateSamples; $repeat++) {
        $candidateCache->invalidateGeneration($generationId);
        $started = hrtime(TRUE);
        $alternativePage = $query->query($filters, $account, NULL, 100);
        $alternativeColdMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
        self::assertNotNull($alternativePage->nextCursor);
      }
      $alternativeWarmMilliseconds = [];
      for ($repeat = 0; $repeat < $candidateSamples; $repeat++) {
        $started = hrtime(TRUE);
        $alternativePage = $query->query($filters, $account, NULL, 100);
        $alternativeWarmMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
        self::assertNotNull($alternativePage->nextCursor);
      }
      sort($alternativeColdMilliseconds, SORT_NUMERIC);
      sort($alternativeWarmMilliseconds, SORT_NUMERIC);
      $alternativeIndexComparison = [
        'index_fields' => ['generation_id', 'domain_id', 'published', 'entry_id'],
        'build_ms' => round($indexBuildMilliseconds, 3),
        'baseline_entry_bytes' => $baselineEntryBytes,
        'alternative_entry_bytes' => $alternativeEntryBytes,
        'allocated_delta_bytes' => max(0, $alternativeEntryBytes - $baselineEntryBytes),
        'writer_sample_size' => 250,
        'baseline_writer_ms' => $baselineWriter['elapsed_ms'],
        'baseline_writer_rows_per_second' => $baselineWriter['rows_per_second'],
        'alternative_writer_ms' => $alternativeWriter['elapsed_ms'],
        'alternative_writer_rows_per_second' => $alternativeWriter['rows_per_second'],
        'cold_page_p50_ms' => round($this->percentile($alternativeColdMilliseconds, 0.50), 3),
        'cold_page_p95_ms' => round($this->percentile($alternativeColdMilliseconds, 0.95), 3),
        'warm_page_p50_ms' => round($this->percentile($alternativeWarmMilliseconds, 0.50), 3),
        'warm_page_p95_ms' => round($this->percentile($alternativeWarmMilliseconds, 0.95), 3),
        'sql_plan' => $this->explainCandidateQuery($generationId),
      ];
      $schema->dropIndex('aculta_index_entry', 'benchmark_filter_entry');
      $candidateCache->invalidateGeneration($generationId);

      $relationResults = [];
      foreach ([1, 5, 20] as $fanout) {
        $database->delete('aculta_index_relation')
          ->condition('generation_id', $generationId)
          ->execute();
        $this->seedRelations($generationId, 10_000, $fanout);
        $relationResults[] = $this->measureRelationReads($generationId, $fanout);
      }
      $tableSizes = [
        'entries_bytes' => $this->tableStorageBytes('aculta_index_entry'),
        'relations_bytes' => $this->tableStorageBytes('aculta_index_relation'),
      ];

      $result = [
        'benchmark' => 'candidate_query',
        'entries' => $size,
        'batch_size' => $batchSize,
        'insert_ms' => round($insertMilliseconds, 3),
        'insert_rows_per_second' => (int) round($size / max($insertMilliseconds / 1000, 0.000001)),
        'candidate_samples' => $candidateSamples,
        'cold_page_p50_ms' => round($this->percentile($coldMilliseconds, 0.50), 3),
        'cold_page_p95_ms' => round($this->percentile($coldMilliseconds, 0.95), 3),
        'warm_page_p50_ms' => round($this->percentile($warmMilliseconds, 0.50), 3),
        'warm_page_p95_ms' => round($this->percentile($warmMilliseconds, 0.95), 3),
        'candidate_sql_plan' => $this->explainCandidateQuery($generationId),
        'alternative_index' => $alternativeIndexComparison,
        'relation_queries' => $relationResults,
        'table_storage_bytes' => $tableSizes,
        'peak_memory_bytes' => memory_get_peak_usage(TRUE),
        'note' => 'Synthetic entity IDs are absent; this measures catalog candidate SQL, bounded failed-source loads, and relation edge reads, not real Entity Access throughput.',
      ];
      $encodedResult = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
      if (file_put_contents($outputFile, $encodedResult, FILE_APPEND | LOCK_EX) === FALSE) {
        throw new \RuntimeException('Could not append a Content Index benchmark result.');
      }

      $database->delete('aculta_index_entry')
        ->condition('generation_id', $generationId)
        ->execute();
      $database->delete('aculta_index_relation')
        ->condition('generation_id', $generationId)
        ->execute();
      $database->delete('aculta_index_cursor')
        ->condition('generation_id', $generationId)
        ->execute();
      $database->delete('aculta_index_generation')
        ->condition('generation_id', $generationId)
        ->execute();
      $database->update('aculta_index_state')
        ->fields(['active_generation' => '', 'revision' => 0])
        ->condition('state_id', 1)
        ->execute();
    }
  }

  /**
   * Creates an active generation without source rows for a catalog-only run.
   */
  private function activateSyntheticGeneration(): string {
    $generationId = $this->container->get('aculta_index.generation_manager')->start();
    $database = $this->container->get('database');
    $database->update('aculta_index_generation')
      ->fields(['status' => 'active'])
      ->condition('generation_id', $generationId)
      ->execute();
    $database->update('aculta_index_state')
      ->fields([
        'active_generation' => $generationId,
        'revision' => 1,
      ])
      ->condition('state_id', 1)
      ->execute();

    return $generationId;
  }

  /**
   * Seeds a bounded relation fan-out sample for database-only measurements.
   */
  private function seedRelations(string $generationId, int $sourceCount, int $fanout): void {
    $database = $this->container->get('database');
    $sourceBatchSize = 100;
    for ($offset = 0; $offset < $sourceCount; $offset += $sourceBatchSize) {
      $insert = $database->insert('aculta_index_relation')
        ->fields([
          'generation_id',
          'provider_id',
          'source_entity_type',
          'source_entity_id',
          'source_langcode',
          'relation_type',
          'target_entity_type',
          'target_entity_id',
          'target_langcode',
          'relation_hash',
          'updated',
        ]);
      $end = min($offset + $sourceBatchSize, $sourceCount);
      for ($source = $offset; $source < $end; $source++) {
        $sourceId = (string) ($source + 1);
        for ($edge = 0; $edge < $fanout; $edge++) {
          $targetId = (string) (10_000_001 + $source * $fanout + $edge);
          $insert->values([
            $generationId,
            'image',
            'media',
            $sourceId,
            'en',
            'related_image',
            'media',
            $targetId,
            'en',
            hash('sha256', $generationId . ':' . $fanout . ':' . $sourceId . ':' . $edge),
            1_700_000_000,
          ]);
        }
      }
      $insert->execute();
    }
  }

  /**
   * Measures bounded keyset reads across representative source identities.
   *
   * @return array{fanout: int, sampled_sources: int, edges: int, p50_ms: float, p95_ms: float}
   *   Relation database measurements.
   */
  private function measureRelationReads(string $generationId, int $fanout): array {
    $database = $this->container->get('database');
    $times = [];
    $sampledSources = 30;
    for ($sample = 0; $sample < $sampledSources; $sample++) {
      $sourceId = (string) (1 + (int) floor($sample * 10_000 / $sampledSources));
      $started = hrtime(TRUE);
      $database->select('aculta_index_relation', 'relation')
        ->fields('relation', ['target_entity_type', 'target_entity_id', 'target_langcode'])
        ->condition('generation_id', $generationId)
        ->condition('source_entity_type', 'media')
        ->condition('source_entity_id', $sourceId)
        ->condition('source_langcode', 'en')
        ->condition('relation_type', 'related_image')
        ->orderBy('target_entity_id')
        ->range(0, 25)
        ->execute()
        ->fetchAll();
      $times[] = (hrtime(TRUE) - $started) / 1_000_000;
    }
    sort($times, SORT_NUMERIC);

    return [
      'fanout' => $fanout,
      'sampled_sources' => $sampledSources,
      'edges' => 10_000 * $fanout,
      'p50_ms' => round($times[(int) floor(($sampledSources - 1) * 0.50)], 3),
      'p95_ms' => round($times[(int) ceil(($sampledSources - 1) * 0.95)], 3),
    ];
  }

  /**
   * Returns a nearest-rank percentile from an already sorted sample.
   *
   * @param list<float> $samples
   *   Sorted measurements.
   * @param float $percentile
   *   Fraction between 0 and 1.
   */
  private function percentile(array $samples, float $percentile): float {
    $index = (int) ceil((count($samples) - 1) * $percentile);
    return $samples[$index];
  }

  /**
   * Reports MariaDB's chosen access path for the actual candidate predicates.
   *
   * @return array<string, mixed>
   *   Sanitized EXPLAIN fields relevant to index selection.
   */
  private function explainCandidateQuery(string $generationId): array {
    $query = $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['entry_id', 'entity_type', 'entity_id', 'langcode'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'image')
      ->condition('provider_version', 1)
      ->condition('entity_type', 'media')
      ->condition('bundle', 'image')
      ->condition('domain_id', 'main')
      ->condition('published', 1)
      ->orderBy('entry_id', 'DESC')
      ->range(0, 100);
    $plan = $this->container->get('database')
      ->query('EXPLAIN ' . (string) $query, $query->arguments())
      ->fetchAssoc();

    return [
      'type' => (string) ($plan['type'] ?? ''),
      'possible_keys' => (string) ($plan['possible_keys'] ?? ''),
      'key' => (string) ($plan['key'] ?? ''),
      'rows' => (int) ($plan['rows'] ?? 0),
      'extra' => (string) ($plan['Extra'] ?? ''),
    ];
  }

  /**
   * Measures real writer inserts in a disposable generation.
   *
   * @return array{elapsed_ms: float, rows_per_second: int}
   *   Writer throughput and elapsed time.
   */
  private function measureWriterSample(int $sampleSize): array {
    $database = $this->container->get('database');
    $generationId = $this->container->get('aculta_index.generation_manager')->start();
    $writer = $this->container->get('aculta_index.writer');
    $started = hrtime(TRUE);
    for ($index = 0; $index < $sampleSize; $index++) {
      $entityId = 'benchmark-writer-' . $index;
      $projection = new ContentIndexProjection(
        'media',
        $entityId,
        'en',
        'image',
        hash('sha256', $generationId . ':' . $entityId),
        'Synthetic benchmark image ' . $index,
        'main',
        TRUE,
        1_700_000_000 + $index,
        1_700_000_000 + $index,
      );
      $writer->write($generationId, 'image', $projection, 1);
    }
    $elapsedMilliseconds = (hrtime(TRUE) - $started) / 1_000_000;
    $database->delete('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->execute();
    $database->delete('aculta_index_generation')
      ->condition('generation_id', $generationId)
      ->execute();

    return [
      'elapsed_ms' => round($elapsedMilliseconds, 3),
      'rows_per_second' => (int) round($sampleSize / max($elapsedMilliseconds / 1000, 0.000001)),
    ];
  }

  /**
   * Returns allocated data and index bytes for one test-prefixed table.
   */
  private function tableStorageBytes(string $table): int {
    $database = $this->container->get('database');
    $tableName = $database->getPrefix() . $table;
    $size = $database->query(
      'SELECT COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
      [':table_name' => $tableName],
    )->fetchField();

    return (int) $size;
  }

}
