<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Rebuild\ContentIndexEntityEnumerator;
use Drupal\aculta_index\Storage\ContentIndexWriter;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Installs and exercises the initial schema in the test database.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexSchemaKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_index'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
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
   * Checks a rebuild can start and terminate cleanly without providers.
   */
  public function testRebuilderStartsEmptyGenerationWhenNoProvidersExist(): void {
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
    $manager->markValidated($first);
    $manager->activate($first);
    $this->assertSame($first, $manager->getActiveGeneration());

    $second = $manager->start();
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
   * Tests the projection writer is idempotent and rejects stale sequences.
   */
  public function testWriterSynchronizesEntryAndRelationsBySequence(): void {
    $generation = $this->container->get('aculta_index.generation_manager')->start();
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('magazine');
    $provider->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);
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
   * Checks the provider registry loads without source adapters.
   */
  public function testProviderRegistryLoadsWithoutSourceAdapters(): void {
    $registry = $this->container->get('aculta_index.provider_registry');

    $this->assertSame([], $registry->all());
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
        'bundle' => 'article',
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
