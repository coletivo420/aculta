<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Rebuild\ContentIndexEntityEnumerator;
use Drupal\aculta_index\Storage\ContentIndexWriter;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Installs and exercises the initial schema in the test database.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexSchemaKernelTest extends KernelTestBase {

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
    // Schema and writer tests do not evaluate Domain-aware Entity Access.
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
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
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
   * Checks an identity is unique per generation, entity and language.
   */
  public function testEntryIdentityAllowsLanguageAndGenerationVariants(): void {
    $this->insertEntry('gen-a', 'node', '123', 'pt-br');
    $this->insertEntry('gen-a', 'node', '123', 'en');
    $this->insertEntry('gen-b', 'node', '123', 'pt-br');

    $this->assertSame(3, (int) $this->container->get('database')
      ->select('aculta_index_entry')
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Checks the state pointer can refer to the active generation.
   */
  public function testActiveGenerationPointerIsStored(): void {
    $this->container->get('database')->update('aculta_index_state')
      ->fields([
        'active_generation' => 'gen-a',
        'revision' => 1,
        'updated' => 100,
      ])
      ->condition('state_id', 1)
      ->execute();

    $this->assertSame('gen-a', $this->container->get('database')
      ->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchField());
  }

  /**
   * Checks a fresh install starts with no published generation.
   */
  public function testInstallInitializesEmptyActiveGenerationPointer(): void {
    $state = $this->container->get('database')->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation', 'revision'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchAssoc();

    $this->assertSame('', $state['active_generation']);
    $this->assertSame('0', $state['revision']);
  }

  /**
   * Checks repeated source events deduplicate into a latest-sequence job.
   */
  public function testWorkManagerDeduplicatesAndClaimsLatestSequence(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $first = $work->schedule('magazine', 'node', '123', 'pt_BR', 'project');
    $second = $work->schedule('magazine', 'node', '123', 'pt_BR', 'delete');

    $this->assertSame(1, $first);
    $this->assertSame(2, $second);
    $this->assertSame(1, (int) $this->container->get('database')->select('aculta_index_work')->countQuery()->execute()->fetchField());
    $status = $work->getStatus('magazine', 'node', '123', 'pt_BR');
    $this->assertSame('delete', $status['operation']);
    $this->assertSame('queued', $status['status']);

    $claim = $work->claim($status['work_id']);
    $this->assertSame(2, $claim['sequence']);
    $this->assertSame('delete', $claim['operation']);
    $this->assertTrue($work->complete($claim['work_id'], $claim['sequence']));
    $this->assertSame('done', $work->getStatus('magazine', 'node', '123', 'pt_BR')['status']);
  }

  /**
   * Checks approved retry count and exponential delay before dead-letter.
   */
  public function testWorkManagerRetriesFiveTimesThenMarksDead(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $work->schedule('magazine', 'node', 'retry-1', 'pt_BR');
    $database = $this->container->get('database');
    $delays = [30, 60, 120, 240];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
      $status = $work->getStatus('magazine', 'node', 'retry-1', 'pt_BR');
      $claim = $work->claim($status['work_id']);
      $this->assertNotNull($claim);
      $this->assertTrue($work->fail($claim['work_id'], $claim['sequence'], 'worker_failure'));
      $status = $work->getStatus('magazine', 'node', 'retry-1', 'pt_BR');
      $this->assertSame($attempt, $status['attempts']);

      if ($attempt < 5) {
        $this->assertSame('retry', $status['status']);
        $this->assertGreaterThanOrEqual($this->container->get('datetime.time')->getRequestTime() + $delays[$attempt - 1], $status['available_at']);
        $database->update('aculta_index_work')->fields(['available_at' => 0])
          ->condition('work_id', $status['work_id'])
          ->execute();
        $this->assertSame(1, $work->recover());
      }
      else {
        $this->assertSame('dead', $status['status']);
        $this->assertSame(0, $status['available_at']);
      }
    }

    $gate = $work->getActivationGateState(['magazine']);
    $this->assertFalse($gate['ready']);
    $this->assertSame(1, $gate['dead']);
    $dead = $work->listDead();
    $this->assertCount(1, $dead);
    $this->assertSame('worker_failure', $dead[0]['last_error_code']);
    $this->assertTrue($work->retryDead($dead[0]['work_id']));
    $status = $work->getStatus('magazine', 'node', 'retry-1', 'pt_BR');
    $this->assertSame('queued', $status['status']);
    $this->assertSame(0, $status['attempts']);
    $claim = $work->claim($status['work_id']);
    $this->assertTrue($work->complete($claim['work_id'], $claim['sequence']));
    $this->assertTrue($work->getActivationGateState(['magazine'])['ready']);
  }

  /**
   * Reclaims worker states whose Core Queue lease expired during processing.
   */
  public function testWorkManagerRecoversStaleProcessingAsRetry(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $work->schedule('magazine', 'node', 'stuck-1', 'pt_BR');
    $status = $work->getStatus('magazine', 'node', 'stuck-1', 'pt_BR');
    $claim = $work->claim($status['work_id']);
    $this->assertNotNull($claim);
    $this->container->get('database')->update('aculta_index_work')
      ->fields(['updated' => $this->container->get('datetime.time')->getRequestTime() - 301])
      ->condition('work_id', $status['work_id'])
      ->execute();

    $this->assertSame(0, $work->recover());
    $status = $work->getStatus('magazine', 'node', 'stuck-1', 'pt_BR');
    $this->assertSame('retry', $status['status']);
    $this->assertSame(1, $status['attempts']);
    $this->assertSame('worker_timeout', $status['last_error_code']);
    $this->assertSame(1, $work->getActivationGateState(['magazine'])['retry']);
  }

  /**
   * Checks a rebuild terminates when providers have no source entities.
   */
  public function testRebuilderStartsEmptyGenerationWhenNoSourceEntitiesExist(): void {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $result = $rebuilder->processNextBatch($generationId);

    $this->assertSame($generationId, $result['generation_id']);
    $this->assertTrue($result['complete']);
    $this->assertSame(0, $result['projected']);
    $this->assertSame('building', $this->container->get('database')->select('aculta_index_generation', 'generation')
      ->fields('generation', ['status'])
      ->condition('generation_id', $generationId)
      ->execute()
      ->fetchField());
  }

  /**
   * Tests generations activate only after validation and can be rolled back.
   */
  public function testGenerationLifecycleAndRollback(): void {
    $manager = $this->container->get('aculta_index.generation_manager');
    $first = $manager->start();
    $manager->markBackfillCompleted($first, 0);
    $manager->markValidated($first);
    $manager->activate($first);
    $this->assertSame($first, $manager->getActiveGeneration());

    $second = $manager->start();
    $manager->markBackfillCompleted($second, 0);
    $manager->markValidated($second);
    $manager->activate($second);
    $this->assertSame($second, $manager->getActiveGeneration());

    $manager->activate($first);
    $this->assertSame($first, $manager->getActiveGeneration());
    $this->assertSame('retained', $this->generationStatus($second));
  }

  /**
   * Tests a checkpoint resumes from the committed cursor and rejects races.
   */
  public function testCheckpointAdvancesWithCompareAndSwap(): void {
    $generation = $this->container->get('aculta_index.generation_manager')->start();
    $manager = $this->container->get('aculta_index.rebuild_checkpoint');

    $initial = $manager->load($generation);
    $this->assertSame('', $initial['entity_id']);
    $this->assertSame(0, $initial['revision']);

    $nextRevision = $manager->advance($generation, 'magazine', 'node', '42', $initial['revision']);
    $this->assertSame(1, $nextRevision);
    $current = $manager->load($generation);
    $this->assertSame('magazine', $current['provider']);
    $this->assertSame('node', $current['entity_type']);
    $this->assertSame('42', $current['entity_id']);
    $this->assertSame(1, $current['revision']);

    try {
      $manager->advance($generation, 'magazine', 'node', '40', $initial['revision']);
      $this->fail('Expected a stale checkpoint writer to be rejected.');
    }
    catch (\RuntimeException) {
      $this->assertSame('42', $manager->load($generation)['entity_id']);
    }
  }

  /**
   * Tests the update hook adds the checkpoint CAS field to older installs.
   */
  public function testUpdateHookAddsCheckpointRevision(): void {
    $database = $this->container->get('database');
    $database->insert('aculta_index_generation')
      ->fields(['generation_id' => 'legacy-gen', 'status' => 'building'])
      ->execute();
    $schema = $database->schema();
    $schema->dropField('aculta_index_generation', 'cursor_revision');
    $this->assertFalse($schema->fieldExists('aculta_index_generation', 'cursor_revision'));

    aculta_index_update_10001();

    $this->assertTrue($schema->fieldExists('aculta_index_generation', 'cursor_revision'));
    $this->assertSame('0', $database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['cursor_revision'])
      ->condition('generation_id', 'legacy-gen')
      ->execute()
      ->fetchField());
  }

  /**
   * Tests the update hook adds the source sequence on older installs.
   */
  public function testUpdateHookAddsSourceSequence(): void {
    $database = $this->container->get('database');
    $this->insertEntry('gen-old', 'node', '104', 'pt-br');
    $schema = $database->schema();
    $schema->dropField('aculta_index_entry', 'source_sequence');
    $this->assertFalse($schema->fieldExists('aculta_index_entry', 'source_sequence'));

    aculta_index_update_10002();

    $this->assertTrue($schema->fieldExists('aculta_index_entry', 'source_sequence'));
    $this->assertSame('0', $database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['source_sequence'])
      ->condition('entity_id', '104')
      ->execute()
      ->fetchField());
  }

  /**
   * Tests the update hook adds provider inventory identity to older installs.
   */
  public function testUpdateHookAddsProviderFingerprint(): void {
    $database = $this->container->get('database');
    $database->insert('aculta_index_generation')
      ->fields(['generation_id' => 'legacy-provider-gen', 'status' => 'building'])
      ->execute();
    $schema = $database->schema();
    $schema->dropField('aculta_index_generation', 'provider_fingerprint');
    $this->assertFalse($schema->fieldExists('aculta_index_generation', 'provider_fingerprint'));

    aculta_index_update_10004();

    $this->assertTrue($schema->fieldExists('aculta_index_generation', 'provider_fingerprint'));
    $this->assertSame('', $database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['provider_fingerprint'])
      ->condition('generation_id', 'legacy-provider-gen')
      ->execute()
      ->fetchField());
  }

  /**
   * Tests the update hook adds backfill completion state to older installs.
   */
  public function testUpdateHookAddsBackfillCompletion(): void {
    $database = $this->container->get('database');
    $database->insert('aculta_index_generation')
      ->fields(['generation_id' => 'legacy-backfill-gen', 'status' => 'building'])
      ->execute();
    $schema = $database->schema();
    $schema->dropField('aculta_index_generation', 'backfill_completed');
    $this->assertFalse($schema->fieldExists('aculta_index_generation', 'backfill_completed'));

    aculta_index_update_10008();

    $this->assertTrue($schema->fieldExists('aculta_index_generation', 'backfill_completed'));
    $this->assertSame('0', $database->select('aculta_index_generation', 'generation')
      ->fields('generation', ['backfill_completed'])
      ->condition('generation_id', 'legacy-backfill-gen')
      ->execute()
      ->fetchField());
  }

  /**
   * Tests the update hook creates and initializes the audit checkpoint table.
   */
  public function testUpdateHookCreatesAuditCheckpoint(): void {
    $database = $this->container->get('database');
    $schema = $database->schema();
    $schema->dropTable('aculta_index_audit');
    $this->assertFalse($schema->tableExists('aculta_index_audit'));

    aculta_index_update_10009();

    $this->assertTrue($schema->tableExists('aculta_index_audit'));
    $this->assertSame('idle', $database->select('aculta_index_audit', 'audit')
      ->fields('audit', ['status'])
      ->condition('audit_id', 1)
      ->execute()
      ->fetchField());
  }

  /**
   * Tests older catalog entries receive the initial projection version.
   */
  public function testUpdateHookAddsProviderProjectionVersion(): void {
    $database = $this->container->get('database');
    $schema = $database->schema();
    $schema->dropField('aculta_index_entry', 'provider_version');
    $this->insertEntry('gen-provider-version', 'node', '217', 'pt-br');
    $this->assertFalse($schema->fieldExists('aculta_index_entry', 'provider_version'));

    aculta_index_update_10010();

    $this->assertTrue($schema->fieldExists('aculta_index_entry', 'provider_version'));
    $this->assertSame('1', $database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['provider_version'])
      ->condition('entity_id', '217')
      ->execute()
      ->fetchField());
  }

  /**
   * Tests existing installations receive the immutable-ID query index.
   */
  public function testUpdateHookReplacesChangedKeysetIndex(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropIndex('aculta_index_entry', 'gen_entry_id');
    $schema->addIndex('aculta_index_entry', 'gen_changed', ['generation_id', 'changed', 'entry_id'], aculta_index_schema()['aculta_index_entry']);
    $this->assertTrue($schema->indexExists('aculta_index_entry', 'gen_changed'));

    aculta_index_update_10011();

    $this->assertFalse($schema->indexExists('aculta_index_entry', 'gen_changed'));
    $this->assertTrue($schema->indexExists('aculta_index_entry', 'gen_entry_id'));
  }

  /**
   * Tests the projection writer is idempotent and rejects stale sequences.
   */
  public function testWriterSynchronizesEntryAndRelationsBySequence(): void {
    $generation = $this->container->get('aculta_index.generation_manager')->start();
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $projectionVersion = 1;
    $provider->method('getProviderId')->willReturn('magazine');
    $provider->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);
    $provider->method('getProjectionVersion')->willReturnCallback(static function () use (&$projectionVersion): int {
      return $projectionVersion;
    });
    $writer = new ContentIndexWriter(
      $this->container->get('database'),
      new ContentIndexProviderRegistry([$provider]),
      $this->container->get('datetime.time'),
    );
    $first = $this->projection('Original', 10, 'activity-1');

    $this->assertTrue($writer->write($generation, 'magazine', $first, 1));
    $this->assertFalse($writer->write($generation, 'magazine', $first, 1));
    $this->assertSame(1, $this->entryCount($generation));
    $this->assertSame(1, $this->relationCount($generation));

    $secondSource = $this->projection('Outra publicação', 10, 'activity-1', '43');
    $this->assertTrue($writer->write($generation, 'magazine', $secondSource, 1));
    $this->assertSame(2, $this->relationCount($generation));

    $latest = $this->projection('Revisada', 11, 'activity-2');
    $this->assertTrue($writer->write($generation, 'magazine', $latest, 2));
    $this->assertFalse($writer->write($generation, 'magazine', $first, 1));
    $this->assertSame('Revisada', $this->entryLabel($generation));
    $this->assertSame('2', $this->entrySequence($generation));
    $this->assertSame('activity-2', $this->relationTarget($generation));
    $this->assertSame(2, $this->relationCount($generation));

    // A projection version bump rewrites a row without a newer source event.
    $projectionVersion = 2;
    $this->assertTrue($writer->write($generation, 'magazine', $latest, 2));
    $this->assertSame('2', $this->entryProviderVersion($generation));

    try {
      $writer->write($generation, 'magazine', $this->projection('Divergente', 11, 'activity-3'), 2);
      $this->fail('Expected conflicting data for one source sequence to be rejected.');
    }
    catch (\LogicException) {
      $this->assertSame('Revisada', $this->entryLabel($generation));
      $this->assertSame('activity-2', $this->relationTarget($generation));
    }

    $this->assertFalse($writer->remove($generation, 'magazine', 'node', '42', 'pt-br', 1));
    $this->assertTrue($writer->remove($generation, 'magazine', 'node', '42', 'pt-br', 3));
    $this->assertSame(1, $this->entryCount($generation));
    $this->assertSame(1, $this->relationCount($generation));
  }

  /**
   * Tests an unvalidated generation cannot replace the active pointer.
   */
  public function testBuildingGenerationCannotActivate(): void {
    $manager = $this->container->get('aculta_index.generation_manager');
    $generation = $manager->start();

    try {
      $manager->markValidated($generation);
      $this->fail('Expected an incomplete backfill to be rejected.');
    }
    catch (\InvalidArgumentException) {
      $this->assertNull($manager->getActiveGeneration());
    }

    try {
      $manager->activate($generation);
      $this->fail('Expected an unvalidated generation to be rejected.');
    }
    catch (\InvalidArgumentException) {
      $this->assertNull($manager->getActiveGeneration());
      $this->assertSame(0, (int) $this->container->get('database')->select('aculta_index_state', 'state')
        ->fields('state', ['revision'])
        ->condition('state_id', 1)
        ->execute()
        ->fetchField());
    }
  }

  /**
   * Checks the registry loads kernel-owned media adapters.
   */
  public function testProviderRegistryLoadsKernelMediaAdapters(): void {
    $registry = $this->container->get('aculta_index.provider_registry');

    $providerIds = array_keys($registry->all());
    sort($providerIds);
    $this->assertSame(['image'], $providerIds);
  }

  /**
   * Checks backfill discovery service is available with no providers.
   */
  public function testEntityEnumeratorServiceLoadsWithoutSourceAdapters(): void {
    $enumerator = $this->container->get('aculta_index.entity_enumerator');

    $this->assertInstanceOf(ContentIndexEntityEnumerator::class, $enumerator);
  }

  /**
   * Tests a fresh catalog is internally consistent with an empty pointer.
   */
  public function testIntegrityCheckerAcceptsEmptyCatalog(): void {
    $result = $this->container->get('aculta_index.integrity_checker')->check();

    $this->assertTrue($result['valid']);
    $this->assertNull($result['active_generation']);
    $this->assertSame(0, $result['orphan_entry_count']);
    $this->assertSame(0, $result['orphan_relation_count']);
  }

  /**
   * Tests missing generation and relation source rows are reported.
   */
  public function testIntegrityCheckerFindsOrphanCatalogRows(): void {
    $database = $this->container->get('database');
    $database->insert('aculta_index_generation')
      ->fields([
        'generation_id' => 'gen-a',
        'status' => 'active',
        'validated' => 1,
        'activated' => 1,
      ])
      ->execute();
    $database->update('aculta_index_state')
      ->fields(['active_generation' => 'gen-a', 'revision' => 1])
      ->condition('state_id', 1)
      ->execute();
    $this->insertEntry('missing-generation', 'node', '101', 'pt-br');
    $database->insert('aculta_index_relation')
      ->fields([
        'generation_id' => 'gen-a',
        'provider_id' => 'magazine',
        'source_entity_type' => 'node',
        'source_entity_id' => '102',
        'source_langcode' => 'pt-br',
        'relation_type' => 'related_activity',
        'target_entity_type' => 'node',
        'target_entity_id' => '202',
        'target_langcode' => 'und',
        'relation_hash' => str_repeat('a', 64),
        'updated' => 1,
      ])
      ->execute();

    $result = $this->container->get('aculta_index.integrity_checker')->check();

    $this->assertFalse($result['valid']);
    $this->assertSame(1, $result['orphan_entry_count']);
    $this->assertSame(0, $result['orphan_relation_count']);
    $this->assertSame(1, $result['relation_source_missing_count']);
  }

  /**
   * Tests incremental audit checkpoints and aggregates without full scans.
   */
  public function testIncrementalIntegrityAuditResumesAndCountsFindings(): void {
    $database = $this->container->get('database');
    $database->insert('aculta_index_generation')
      ->fields([
        'generation_id' => 'audit-generation',
        'status' => 'active',
        'validated' => 1,
        'activated' => 1,
      ])
      ->execute();
    $database->update('aculta_index_state')
      ->fields(['active_generation' => 'audit-generation', 'revision' => 1])
      ->condition('state_id', 1)
      ->execute();
    $this->insertEntry('audit-generation', 'node', '301', 'pt-br');
    $this->insertEntry('orphan-generation', 'node', '302', 'pt-br');
    $database->insert('aculta_index_relation')
      ->fields([
        'generation_id' => 'audit-generation',
        'provider_id' => 'magazine',
        'source_entity_type' => 'node',
        'source_entity_id' => 'missing-source',
        'source_langcode' => 'pt-br',
        'relation_type' => 'related_activity',
        'target_entity_type' => 'node',
        'target_entity_id' => '999',
        'target_langcode' => 'und',
        'relation_hash' => str_repeat('b', 64),
        'updated' => 1,
      ])
      ->execute();

    $checker = $this->container->get('aculta_index.integrity_checker');
    $started = $checker->startAudit();
    $this->assertSame('running', $started['status']);
    $this->assertSame('entries', $started['phase']);

    $firstBatch = $checker->processAuditBatch(1);
    $this->assertSame('entries', $firstBatch['phase']);
    $this->assertSame(1, (int) $firstBatch['cursor_id']);

    $state = $firstBatch;
    while ($state['status'] === 'running') {
      $state = $checker->processAuditBatch(1);
    }
    $this->assertSame('complete', $state['status']);
    $this->assertFalse($state['valid']);
    $this->assertSame(1, (int) $state['orphan_entry_count']);
    $this->assertSame(0, (int) $state['orphan_relation_count']);
    $this->assertSame(1, (int) $state['relation_source_missing_count']);
  }

  /**
   * Tests cron advances batches and waits a day before starting another cycle.
   */
  public function testScheduledAuditRunsOneBatchAndHonorsDailyInterval(): void {
    $checker = $this->container->get('aculta_index.integrity_checker');
    $first = $checker->runScheduledAuditBatch(1);
    $this->assertSame('running', $first['status']);

    $state = $first;
    while ($state['status'] === 'running') {
      $state = $checker->runScheduledAuditBatch(1);
    }
    $this->assertSame('complete', $state['status']);
    $completed = (int) $state['completed'];

    $sameCycle = $checker->runScheduledAuditBatch(1);
    $this->assertSame('complete', $sameCycle['status']);
    $this->assertSame($completed, (int) $sameCycle['completed']);

    $this->container->get('database')->update('aculta_index_audit')
      ->fields(['completed' => $completed - 86400])
      ->condition('audit_id', 1)
      ->execute();
    $newCycle = $checker->runScheduledAuditBatch(1);
    $this->assertSame('running', $newCycle['status']);
  }

  /**
   * Checks a duplicate entity and language cannot be stored in one generation.
   */
  public function testEntryIdentityIsUniqueWithinGeneration(): void {
    $this->insertEntry('gen-a', 'node', '123', 'pt-br');

    $this->expectException(IntegrityConstraintViolationException::class);
    $this->insertEntry('gen-a', 'node', '123', 'pt-br');
  }

  /**
   * Inserts a minimal entry fixture.
   */
  private function insertEntry(string $generation, string $entityType, string $entityId, string $langcode): void {
    $this->container->get('database')->insert('aculta_index_entry')
      ->fields([
        'generation_id' => $generation,
        'provider_id' => 'fixture',
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'langcode' => $langcode,
        'bundle' => 'sample_content',
        'uuid' => '',
        'label' => 'Fixture',
        'domain_id' => '',
        'created' => 1,
        'changed' => 1,
        'published_at' => 0,
        'projection_hash' => 'fixture-hash',
        'updated' => 1,
      ])
      ->execute();
  }

  /**
   * Gets the stored lifecycle status of a generation.
   */
  private function generationStatus(string $generationId): string {
    return (string) $this->container->get('database')->select('aculta_index_generation', 'generation')
      ->fields('generation', ['status'])
      ->condition('generation_id', $generationId)
      ->execute()
      ->fetchField();
  }

  /**
   * Creates a provider-owned projection fixture.
   */
  private function projection(string $label, int $changed, string $activityId, string $entityId = '42'): ContentIndexProjection {
    return new ContentIndexProjection(
      'node',
      $entityId,
      'pt-br',
      'revista',
      'magazine-uuid',
      $label,
      'coletivo420_aculta_org',
      TRUE,
      1,
      $changed,
      NULL,
      [new ContentIndexRelation('related_activity', 'node', $activityId)],
    );
  }

  /**
   * Counts entries for a generation.
   */
  private function entryCount(string $generationId): int {
    return (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Counts relations for a generation.
   */
  private function relationCount(string $generationId): int {
    return (int) $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Reads the indexed label for the fixture identity.
   */
  private function entryLabel(string $generationId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('entity_id', '42')
      ->execute()
      ->fetchField();
  }

  /**
   * Reads the indexed source sequence for the fixture identity.
   */
  private function entrySequence(string $generationId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['source_sequence'])
      ->condition('generation_id', $generationId)
      ->condition('entity_id', '42')
      ->execute()
      ->fetchField();
  }

  /**
   * Reads the provider projection version for the fixture identity.
   */
  private function entryProviderVersion(string $generationId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['provider_version'])
      ->condition('generation_id', $generationId)
      ->condition('entity_id', '42')
      ->execute()
      ->fetchField();
  }

  /**
   * Reads the first outgoing relation target.
   */
  private function relationTarget(string $generationId): string {
    return (string) $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->fields('relation', ['target_entity_id'])
      ->condition('generation_id', $generationId)
      ->condition('source_entity_id', '42')
      ->execute()
      ->fetchField();
  }

}
