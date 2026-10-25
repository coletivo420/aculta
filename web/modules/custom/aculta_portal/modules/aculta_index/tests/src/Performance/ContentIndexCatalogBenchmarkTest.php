<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Performance;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_index\Cache\ContentIndexCandidateCache;
use Drupal\aculta_index\Query\ContentIndexQueryInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
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
  use MediaTypeCreationTrait;

  /**
   * File entity referenced by the real Media access fixtures.
   */
  private string $benchmarkImageFileId;

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
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['user', 'system', 'image', 'file', 'media', 'entity_usage']);
    $this->createMediaType('image', ['id' => 'image']);
    $file = File::create(['uri' => 'public://aculta-index-benchmark.png']);
    $file->setPermanent();
    $file->save();
    $this->benchmarkImageFileId = (string) $file->id();
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

    $account = $this->setUpCurrentUser([], ['view media']);
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

      // Compare two plausible indexes without changing the module schema. The
      // Kernel table is test-prefixed and each index is dropped after its run.
      $alternativeIndexes = [];
      foreach ([
        'benchmark_filter_entry' => ['generation_id', 'domain_id', 'published', 'entry_id'],
        'benchmark_type_bundle_domain' => [
          'generation_id',
          'entity_type',
          'bundle',
          'domain_id',
          'published',
          'entry_id',
        ],
      ] as $indexName => $indexFields) {
        $alternativeIndexes[] = $this->measureIndexVariant(
          $indexName,
          $indexFields,
          $generationId,
          $filters,
          $account,
          $query,
          $candidateCache,
          $candidateSamples,
        );
      }

      $realAccessResults = $this->measureRealEntityAccessQueries(
        $generationId,
        $size,
        $account,
        $query,
        $candidateCache,
        $candidateSamples,
      );

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
        'alternative_indexes' => $alternativeIndexes,
        'real_entity_access' => $realAccessResults,
        'relation_queries' => $relationResults,
        'table_storage_bytes' => $tableSizes,
        'peak_memory_bytes' => memory_get_peak_usage(TRUE),
        'note' => 'Catalog timing uses synthetic absent IDs. A separate sample loads 25 real Media entities and checks Entity Access for each query.',
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
   * Measures a temporary candidate index against the unchanged base schema.
   *
   * @param string $indexName
   *   Temporary schema index name.
   * @param string[] $indexFields
   *   Ordered index columns.
   * @param string $generationId
   *   Synthetic generation being measured.
   * @param array $filters
   *   Candidate query filters.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account used by the public query service.
   * @param \Drupal\aculta_index\Query\ContentIndexQueryInterface $query
   *   Public candidate query service.
   * @param \Drupal\aculta_index\Cache\ContentIndexCandidateCache $candidateCache
   *   Candidate page cache.
   * @param int $candidateSamples
   *   Cold and warm samples to collect.
   *
   * @return array
   *   Measurements for this index variant.
   */
  private function measureIndexVariant(
    string $indexName,
    array $indexFields,
    string $generationId,
    array $filters,
    AccountInterface $account,
    ContentIndexQueryInterface $query,
    ContentIndexCandidateCache $candidateCache,
    int $candidateSamples,
  ): array {
    $database = $this->container->get('database');
    $schema = $database->schema();
    $writerSampleSize = 1_000;
    $baselineWriter = $this->measureWriterSample($writerSampleSize);
    $database->query('ANALYZE TABLE {aculta_index_entry}')->fetchAll();
    $baselineEntryBytes = $this->tableStorageBytes('aculta_index_entry');
    $indexBuildStarted = hrtime(TRUE);
    $schema->addIndex(
      'aculta_index_entry',
      $indexName,
      $indexFields,
      aculta_index_schema()['aculta_index_entry'],
    );
    $indexBuildMilliseconds = (hrtime(TRUE) - $indexBuildStarted) / 1_000_000;
    $database->query('ANALYZE TABLE {aculta_index_entry}')->fetchAll();
    $alternativeEntryBytes = $this->tableStorageBytes('aculta_index_entry');
    $alternativeWriter = $this->measureWriterSample($writerSampleSize);
    $coldMilliseconds = [];
    for ($repeat = 0; $repeat < $candidateSamples; $repeat++) {
      $candidateCache->invalidateGeneration($generationId);
      $started = hrtime(TRUE);
      $page = $query->query($filters, $account, NULL, 100);
      $coldMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
      self::assertNotNull($page->nextCursor);
    }
    $warmMilliseconds = [];
    for ($repeat = 0; $repeat < $candidateSamples; $repeat++) {
      $started = hrtime(TRUE);
      $page = $query->query($filters, $account, NULL, 100);
      $warmMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
      self::assertNotNull($page->nextCursor);
    }
    sort($coldMilliseconds, SORT_NUMERIC);
    sort($warmMilliseconds, SORT_NUMERIC);
    $comparison = [
      'index_fields' => $indexFields,
      'build_ms' => round($indexBuildMilliseconds, 3),
      'baseline_entry_bytes' => $baselineEntryBytes,
      'alternative_entry_bytes' => $alternativeEntryBytes,
      'allocated_delta_bytes' => max(0, $alternativeEntryBytes - $baselineEntryBytes),
      'writer_sample_size' => $writerSampleSize,
      'baseline_writer_ms' => $baselineWriter['insert_elapsed_ms'],
      'baseline_writer_rows_per_second' => $baselineWriter['insert_rows_per_second'],
      'baseline_writer_idempotent_ms' => $baselineWriter['idempotent_elapsed_ms'],
      'baseline_writer_idempotent_rows_per_second' => $baselineWriter['idempotent_rows_per_second'],
      'baseline_writer_update_ms' => $baselineWriter['update_elapsed_ms'],
      'baseline_writer_update_rows_per_second' => $baselineWriter['update_rows_per_second'],
      'alternative_writer_ms' => $alternativeWriter['insert_elapsed_ms'],
      'alternative_writer_rows_per_second' => $alternativeWriter['insert_rows_per_second'],
      'alternative_writer_idempotent_ms' => $alternativeWriter['idempotent_elapsed_ms'],
      'alternative_writer_idempotent_rows_per_second' => $alternativeWriter['idempotent_rows_per_second'],
      'alternative_writer_update_ms' => $alternativeWriter['update_elapsed_ms'],
      'alternative_writer_update_rows_per_second' => $alternativeWriter['update_rows_per_second'],
      'cold_page_p50_ms' => round($this->percentile($coldMilliseconds, 0.50), 3),
      'cold_page_p95_ms' => round($this->percentile($coldMilliseconds, 0.95), 3),
      'warm_page_p50_ms' => round($this->percentile($warmMilliseconds, 0.50), 3),
      'warm_page_p95_ms' => round($this->percentile($warmMilliseconds, 0.95), 3),
      'sql_plan' => $this->explainCandidateQuery($generationId),
    ];
    $schema->dropIndex('aculta_index_entry', $indexName);
    $candidateCache->invalidateGeneration($generationId);

    return $comparison;
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
   * Measures inserts, idempotent writes and updates in a disposable generation.
   *
   * @return array<string, int|float>
   *   Writer timings and throughput by operation.
   */
  private function measureWriterSample(int $sampleSize): array {
    $database = $this->container->get('database');
    $generationId = $this->container->get('aculta_index.generation_manager')->start();
    $writer = $this->container->get('aculta_index.writer');
    $projections = [];
    for ($index = 0; $index < $sampleSize; $index++) {
      $entityId = 'benchmark-writer-' . $index;
      $projections[] = new ContentIndexProjection(
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
    }

    $insertElapsed = $this->measureWriterOperation(static function () use ($projections, $generationId, $writer): void {
      foreach ($projections as $projection) {
        $writer->write($generationId, 'image', $projection, 1);
      }
    });
    $idempotentElapsed = $this->measureWriterOperation(static function () use ($projections, $generationId, $writer): void {
      foreach ($projections as $projection) {
        if ($writer->write($generationId, 'image', $projection, 1)) {
          throw new \LogicException('An identical Content Index writer replay must be idempotent.');
        }
      }
    });
    $updatedProjections = array_map(static fn (ContentIndexProjection $projection): ContentIndexProjection => new ContentIndexProjection(
      $projection->entityTypeId,
      $projection->entityId,
      $projection->langcode,
      $projection->bundle,
      $projection->uuid,
      $projection->label . ' updated',
      $projection->domainId,
      $projection->published,
      $projection->created,
      $projection->changed + 1,
      $projection->publishedAt,
      $projection->relations,
    ), $projections);
    $updateElapsed = $this->measureWriterOperation(static function () use ($updatedProjections, $generationId, $writer): void {
      foreach ($updatedProjections as $projection) {
        $writer->write($generationId, 'image', $projection, 2);
      }
    });
    $database->delete('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->execute();
    $database->delete('aculta_index_generation')
      ->condition('generation_id', $generationId)
      ->execute();

    return [
      'sample_size' => $sampleSize,
      'insert_elapsed_ms' => round($insertElapsed, 3),
      'insert_rows_per_second' => $this->rowsPerSecond($sampleSize, $insertElapsed),
      'idempotent_elapsed_ms' => round($idempotentElapsed, 3),
      'idempotent_rows_per_second' => $this->rowsPerSecond($sampleSize, $idempotentElapsed),
      'update_elapsed_ms' => round($updateElapsed, 3),
      'update_rows_per_second' => $this->rowsPerSecond($sampleSize, $updateElapsed),
    ];
  }

  /**
   * Measures the elapsed time of one complete writer operation sample.
   *
   * @param callable(): void $operation
   *   Writer operation to run.
   */
  private function measureWriterOperation(callable $operation): float {
    $started = hrtime(TRUE);
    $operation();
    return (hrtime(TRUE) - $started) / 1_000_000;
  }

  /**
   * Converts elapsed milliseconds to whole rows per second.
   */
  private function rowsPerSecond(int $rows, float $elapsedMilliseconds): int {
    return (int) round($rows / max($elapsedMilliseconds / 1000, 0.000001));
  }

  /**
   * Measures query access revalidation against real, mixed-publication Media.
   *
   * @return array<string, int|float>
   *   Candidate counts and cold/warm query percentiles.
   */
  private function measureRealEntityAccessQueries(
    string $generationId,
    int $catalogSize,
    AccountInterface $account,
    ContentIndexQueryInterface $query,
    ContentIndexCandidateCache $candidateCache,
    int $sampleCount,
  ): array {
    $mediaStorage = $this->container->get('entity_type.manager')->getStorage('media');
    $mediaIds = [];
    $expectedCandidates = 0;
    $expectedAuthorized = 0;
    for ($offset = 0; $offset < 100; $offset++) {
      $sourceIndex = $catalogSize - 100 + $offset;
      $mediaId = $sourceIndex + 1;
      $published = $sourceIndex % 8 === 0;
      $media = Media::create([
        'mid' => $mediaId,
        'bundle' => 'image',
        'name' => 'Real access fixture ' . $mediaId,
        'langcode' => 'en',
        'uid' => $account->id(),
        'status' => $published,
        'field_media_image' => [
          'target_id' => $this->benchmarkImageFileId,
          'alt' => 'Synthetic access fixture',
        ],
      ]);
      $media->save();
      $mediaIds[] = (string) $mediaId;
      if ($sourceIndex % 4 === 0) {
        $expectedCandidates++;
        if ($published) {
          $expectedAuthorized++;
        }
      }
    }

    $filters = [
      'entity_type' => 'media',
      'bundle' => 'image',
      'domain_id' => 'main',
    ];
    // Warm one-time Drupal metadata/config loads outside the sample.
    $candidateCache->invalidateGeneration($generationId);
    $mediaStorage->resetCache($mediaIds);
    $query->query($filters, $account, NULL, 100);
    $candidateCache->invalidateGeneration($generationId);
    $mediaStorage->resetCache($mediaIds);
    Database::startLog('aculta_index_access_cold');
    $query->query($filters, $account, NULL, 100);
    $coldQueryCount = count(Database::getLog('aculta_index_access_cold'));
    $mediaStorage->resetCache($mediaIds);
    Database::startLog('aculta_index_access_warm');
    $query->query($filters, $account, NULL, 100);
    $warmQueryCount = count(Database::getLog('aculta_index_access_warm'));

    $coldMilliseconds = [];
    for ($sample = 0; $sample < $sampleCount; $sample++) {
      $candidateCache->invalidateGeneration($generationId);
      $mediaStorage->resetCache($mediaIds);
      $started = hrtime(TRUE);
      $page = $query->query($filters, $account, NULL, 100);
      $coldMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
      self::assertCount($expectedAuthorized, $page->entities);
    }
    $warmMilliseconds = [];
    for ($sample = 0; $sample < $sampleCount; $sample++) {
      $mediaStorage->resetCache($mediaIds);
      $started = hrtime(TRUE);
      $page = $query->query($filters, $account, NULL, 100);
      $warmMilliseconds[] = (hrtime(TRUE) - $started) / 1_000_000;
      self::assertCount($expectedAuthorized, $page->entities);
    }
    sort($coldMilliseconds, SORT_NUMERIC);
    sort($warmMilliseconds, SORT_NUMERIC);

    return [
      'source_entities_available' => count($mediaIds),
      'candidate_entities_per_query' => $expectedCandidates,
      'authorized_entities_per_query' => $expectedAuthorized,
      'denied_entities_per_query' => $expectedCandidates - $expectedAuthorized,
      'query_samples' => $sampleCount,
      'sql_queries_cold_sample' => $coldQueryCount,
      'sql_queries_warm_sample' => $warmQueryCount,
      'cold_page_p50_ms' => round($this->percentile($coldMilliseconds, 0.50), 3),
      'cold_page_p95_ms' => round($this->percentile($coldMilliseconds, 0.95), 3),
      'warm_page_p50_ms' => round($this->percentile($warmMilliseconds, 0.50), 3),
      'warm_page_p95_ms' => round($this->percentile($warmMilliseconds, 0.95), 3),
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
