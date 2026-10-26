<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Exercises a representative mixed catalog: types, languages, states, links.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexRepresentativeKernelTest extends KernelTestBase {

  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'language',
    'content_translation',
    'aculta_index',
    'aculta_index_representative_test',
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
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['system', 'user', 'node', 'language', 'content_translation']);
    aculta_index_install();
    NodeType::create(['type' => 'representative_content', 'name' => 'Representative content'])->save();
    NodeType::create(['type' => 'representative_target', 'name' => 'Representative target'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_representative_domain',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    foreach (['representative_content', 'representative_target'] as $bundle) {
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => $bundle,
        'field_name' => 'field_representative_domain',
        'label' => 'Representative domain',
      ])->save();
    }
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_representative_related',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => -1,
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'representative_content',
      'field_name' => 'field_representative_related',
      'label' => 'Representative targets',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['representative_target' => 'representative_target']],
      ],
    ])->save();
    $this->createLanguageFromLangcode('pt-br');
    $this->enableContentTranslation('node', 'representative_content');
    $this->enableContentTranslation('node', 'representative_target');
    Role::load(Role::AUTHENTICATED_ID)->grantPermission('access content')->save();
  }

  /**
   * Builds, audits and queries a representative catalog with current access.
   */
  public function testRepresentativeRebuildAuditAndAccess(): void {
    User::create(['name' => 'representative-root-fixture', 'status' => 1])->save();
    $author = User::create(['name' => 'representative-author', 'status' => 1, 'roles' => ['authenticated']]);
    $author->save();
    $reader = User::create(['name' => 'representative-reader', 'status' => 1, 'roles' => ['authenticated']]);
    $reader->save();

    $targets = [];
    for ($index = 1; $index <= 12; $index++) {
      $targets[$index] = Node::create([
        'type' => 'representative_target',
        'title' => 'Representative target ' . $index,
        'status' => $index <= 8,
        'uid' => $author->id(),
        'langcode' => 'en',
        'field_representative_domain' => $index % 2 === 0 ? 'wiki' : 'main',
      ]);
      $targets[$index]->save();
    }
    foreach ([1, 2] as $index) {
      $targets[$index]->addTranslation('pt-br', [
        'title' => 'Alvo representativo ' . $index,
        'status' => TRUE,
      ]);
      $targets[$index]->save();
    }

    $primary = [];
    for ($index = 1; $index <= 24; $index++) {
      $related = [$targets[($index % 12) + 1]->id()];
      if ($index === 1) {
        // One draft target exercises target access revalidation.
        $related[] = $targets[9]->id();
      }
      $primary[$index] = Node::create([
        'type' => 'representative_content',
        'title' => 'Representative content ' . $index,
        'status' => $index <= 16,
        'uid' => $author->id(),
        'langcode' => 'en',
        'field_representative_domain' => $index % 2 === 0 ? 'wiki' : 'main',
        'field_representative_related' => array_map(
          static fn (int $id): array => ['target_id' => $id],
          $related,
        ),
      ]);
      $primary[$index]->save();
    }
    foreach ([1, 2, 3, 4] as $index) {
      $primary[$index]->addTranslation('pt-br', [
        'title' => 'Conteúdo representativo ' . $index,
        'status' => TRUE,
        'field_representative_related' => $primary[$index]->get('field_representative_related')->getValue(),
      ]);
      $primary[$index]->save();
    }

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $rebuildStarted = hrtime(TRUE);
    $complete = FALSE;
    for ($batch = 0; $batch < 50 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 100)['complete'];
    }
    self::assertTrue($complete, 'The representative backfill should complete.');
    $this->processAllQueuedWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    $rebuildMilliseconds = (hrtime(TRUE) - $rebuildStarted) / 1_000_000;

    $database = $this->container->get('database');
    $countEntries = static fn (string $provider): int => (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', $provider)
      ->countQuery()
      ->execute()
      ->fetchField();
    // 24 content entities + 4 translations; 12 targets + 2 translations.
    $builtContentEntries = $countEntries('representative_content');
    $builtTargetEntries = $countEntries('representative_target');
    $builtRelations = (int) $database->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'representative_content')
      ->countQuery()
      ->execute()
      ->fetchField();
    self::assertSame(28, $builtContentEntries);
    self::assertSame(14, $builtTargetEntries);
    // 25 English relations (one source has two targets) + 5 translations.
    self::assertSame(30, $builtRelations);

    $query = $this->container->get('aculta_index.query');
    $filters = ['entity_type' => 'node', 'bundle' => 'representative_content'];
    $publishedPage = $query->query($filters + ['published' => TRUE], $reader, NULL, 100);
    self::assertCount(20, $publishedPage->entities);
    foreach ($publishedPage->entities as $entity) {
      self::assertTrue($entity->isPublished());
    }
    $draftPage = $query->query($filters + ['published' => FALSE], $reader, NULL, 100);
    self::assertSame([], $draftPage->entities, 'Draft candidates are never exposed to a regular reader.');

    $wikiExpected = (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'representative_content')
      ->condition('bundle', 'representative_content')
      ->condition('domain_id', 'wiki')
      ->condition('published', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    $wikiPage = $query->query($filters + ['domain_id' => 'wiki', 'published' => TRUE], $reader, NULL, 100);
    self::assertCount($wikiExpected, $wikiPage->entities);
    foreach ($wikiPage->entities as $entity) {
      self::assertSame('wiki', $entity->get('field_representative_domain')->value);
    }

    $relationQuery = $this->container->get('aculta_index.relation_query');
    $relations = $relationQuery->queryOutgoing(
      'node',
      (string) $primary[1]->id(),
      'en',
      'representative_related',
      $reader,
    );
    self::assertSame([(string) $targets[2]->id()], array_map(
      static fn ($entity): string => (string) $entity->id(),
      $relations->entities,
    ), 'Only the published related target is exposed.');

    $seen = [];
    $cursor = NULL;
    $pages = 0;
    do {
      $page = $query->query($filters + ['published' => TRUE], $reader, $cursor, 5);
      foreach ($page->entities as $entity) {
        $key = $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $entity->language()->getId();
        self::assertArrayNotHasKey($key, $seen, 'A cursor page must not repeat an identity.');
        $seen[$key] = TRUE;
      }
      $cursor = $page->nextCursor;
      $pages++;
    } while ($cursor !== NULL && $pages < 20);
    self::assertCount(20, $seen);

    $primary[1]->setTitle('Representative content updated');
    $primary[1]->save();
    $primary[16]->delete();
    $this->processAllQueuedWork();
    self::assertSame('Representative content updated', (string) $database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'representative_content')
      ->condition('entity_id', (string) $primary[1]->id())
      ->condition('langcode', 'en')
      ->execute()
      ->fetchField());
    self::assertSame(0, (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'representative_content')
      ->condition('entity_id', (string) $primary[16]->id())
      ->countQuery()
      ->execute()
      ->fetchField());
    self::assertSame(0, (int) $database->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->condition('source_entity_id', (string) $primary[16]->id())
      ->countQuery()
      ->execute()
      ->fetchField());

    $checker = $this->container->get('aculta_index.integrity_checker');
    $auditStarted = hrtime(TRUE);
    $checker->startAudit();
    $state = $checker->getAuditState();
    while ($state['status'] === 'running') {
      $state = $checker->processAuditBatch(200);
    }
    $auditMilliseconds = (hrtime(TRUE) - $auditStarted) / 1_000_000;
    self::assertSame('complete', $state['status']);
    self::assertTrue($state['valid'], 'The representative catalog must be internally consistent.');
    self::assertSame(0, (int) $state['stale_projection_count']);
    self::assertSame(0, (int) $state['missing_catalog_entry_count']);

    // A residual row from an unregistered owner is never a candidate.
    $database->insert('aculta_index_entry')
      ->fields([
        'generation_id' => $generationId,
        'provider_id' => 'retired_owner',
        'entity_type' => 'node',
        'entity_id' => '999999',
        'langcode' => 'en',
        'bundle' => 'representative_content',
        'uuid' => '',
        'label' => 'Retired owner residual',
        'domain_id' => '',
        'created' => 1,
        'changed' => 1,
        'published' => 1,
        'published_at' => 0,
        'projection_hash' => str_repeat('f', 64),
        'updated' => 1,
      ])
      ->execute();
    $afterResidual = $query->query($filters + ['published' => TRUE], $reader, NULL, 100);
    self::assertCount(19, $afterResidual->entities, 'The deleted source and the retired owner are absent.');

    // Measurement is opt-in; correctness assertions above do not depend on it.
    $coldStarted = hrtime(TRUE);
    $this->container->get('aculta_index.candidate_cache')->invalidateGeneration($generationId);
    $query->query($filters + ['published' => TRUE], $reader, NULL, 100);
    $coldMilliseconds = (hrtime(TRUE) - $coldStarted) / 1_000_000;
    for ($warmup = 0; $warmup < 5; $warmup++) {
      $query->query($filters + ['published' => TRUE], $reader, NULL, 100);
    }
    $samples = [];
    for ($sample = 0; $sample < 30; $sample++) {
      $started = hrtime(TRUE);
      $query->query($filters + ['published' => TRUE], $reader, NULL, 100);
      $samples[] = (hrtime(TRUE) - $started) / 1_000_000;
    }
    $warmP50 = $this->percentile($samples, 50);
    $warmP95 = $this->percentile($samples, 95);
    self::assertGreaterThan(0, $warmP50);
    self::assertGreaterThanOrEqual($warmP50, $warmP95);

    if (getenv('ACULTA_INDEX_REPRESENTATIVE_BENCHMARK') === '1') {
      $finalContentEntries = $countEntries('representative_content');
      $finalTargetEntries = $countEntries('representative_target');
      $finalRelations = (int) $database->select('aculta_index_relation', 'relation')
        ->condition('generation_id', $generationId)
        ->condition('provider_id', 'representative_content')
        ->countQuery()
        ->execute()
        ->fetchField();
      $payload = [
        'benchmark' => 'representative_mixed_catalog',
        'date' => gmdate('Y-m-d'),
        'drupal' => \Drupal::VERSION,
        'php' => PHP_VERSION,
        'built' => [
          'representative_content' => $builtContentEntries,
          'representative_target' => $builtTargetEntries,
          'relations' => $builtRelations,
        ],
        'final' => [
          'representative_content' => $finalContentEntries,
          'representative_target' => $finalTargetEntries,
          'relations' => $finalRelations,
          'published_page' => count($afterResidual->entities),
        ],
        'rebuild_ms' => round($rebuildMilliseconds, 3),
        'audit_ms' => round($auditMilliseconds, 3),
        'cold_query_ms' => round($coldMilliseconds, 3),
        'warm_p50_ms' => round($warmP50, 3),
        'warm_p95_ms' => round($warmP95, 3),
        'peak_memory_bytes' => memory_get_peak_usage(TRUE),
      ];
      file_put_contents(
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
          . 'aculta-index-representative-' . getmypid() . '.json',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      );
    }
  }

  /**
   * Processes every queued Content Index work item through the real worker.
   */
  private function processAllQueuedWork(): void {
    $database = $this->container->get('database');
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('status', 'queued')
      ->orderBy('work_id')
      ->execute()
      ->fetchCol();
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Returns a nearest-rank percentile for the measured query samples.
   *
   * @param list<float> $values
   *   Measured milliseconds.
   * @param float $percentile
   *   Percentile from 0 through 100.
   */
  private function percentile(array $values, float $percentile): float {
    sort($values);
    $index = (int) ceil(($percentile / 100) * count($values)) - 1;
    return $values[max(0, min($index, count($values) - 1))];
  }

}
