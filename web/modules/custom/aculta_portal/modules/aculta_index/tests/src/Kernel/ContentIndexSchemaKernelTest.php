<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_index\Query\ContentIndexCursorManager;
use Drupal\aculta_index\Integrity\ContentIndexIntegrityChecker;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
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
use Psr\Log\NullLogger;
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
   * Expired cursors are rejected and removed when presented again.
   */
  public function testExpiredCursorIsRejectedAndDeleted(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);
    $manager = new ContentIndexCursorManager($this->container->get('database'), $time);
    $queryHash = str_repeat('a', 64);
    $token = $manager->create(7, 'generation-a', $queryHash, 0, 1);
    $tokenHash = hash('sha256', $token);
    $this->container->get('database')->update('aculta_index_cursor')
      ->fields(['expires' => 1000])
      ->condition('token_hash', $tokenHash)
      ->execute();

    try {
      $manager->load($token, 7, 'generation-a', $queryHash);
      $this->fail('An expired cursor must not be resumed.');
    }
    catch (\InvalidArgumentException) {
      $this->assertSame(0, (int) $this->container->get('database')->select('aculta_index_cursor')
        ->condition('token_hash', $tokenHash)
        ->countQuery()
        ->execute()
        ->fetchField());
    }
  }

  /**
   * Cursor tokens are opaque and bound to their account and query.
   */
  public function testCursorTokenIsOpaqueAndBoundToAccountAndQuery(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);
    $database = $this->container->get('database');
    $manager = new ContentIndexCursorManager($database, $time);
    $queryHash = str_repeat('c', 64);
    $token = $manager->create(7, 'generation-a', $queryHash, 0, 987654321);

    $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
    $this->assertTrue($database->schema()->fieldExists('aculta_index_cursor', 'token_hash'));
    $this->assertFalse($database->schema()->fieldExists('aculta_index_cursor', 'token'));
    $row = $database->select('aculta_index_cursor', 'cursor')
      ->fields('cursor', ['token_hash', 'uid', 'generation_id', 'query_hash', 'cursor_entry_id'])
      ->condition('token_hash', hash('sha256', $token))
      ->execute()
      ->fetchAssoc();
    $this->assertIsArray($row);
    $this->assertSame(64, strlen($row['token_hash']));
    $this->assertSame('7', (string) $row['uid']);
    $this->assertSame('generation-a', $row['generation_id']);
    $this->assertSame($queryHash, $row['query_hash']);
    $this->assertSame(987654321, (int) $row['cursor_entry_id']);
    $this->assertSame(987654321, $manager->load($token, 7, 'generation-a', $queryHash)['entry_id']);

    foreach ([
      ['uid' => 8, 'generation' => 'generation-a', 'query' => $queryHash],
      ['uid' => 7, 'generation' => 'generation-b', 'query' => $queryHash],
      ['uid' => 7, 'generation' => 'generation-a', 'query' => str_repeat('d', 64)],
    ] as $invalidBinding) {
      try {
        $manager->load($token, $invalidBinding['uid'], $invalidBinding['generation'], $invalidBinding['query']);
        $this->fail('A cursor must not be transferable to another account, generation or query.');
      }
      catch (\InvalidArgumentException) {
        // Expected: all cursor bindings must match.
      }
    }
  }

  /**
   * Cursor pruning deletes no more than the requested bounded batch.
   */
  public function testCursorPruningDeletesExpiredRowsInBoundedBatches(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);
    $database = $this->container->get('database');
    $manager = new ContentIndexCursorManager($database, $time);
    $queryHash = str_repeat('b', 64);
    $tokens = [];
    for ($entryId = 1; $entryId <= 3; $entryId++) {
      $tokens[] = $manager->create(7, 'generation-a', $queryHash, 0, $entryId);
    }
    $futureToken = $manager->create(7, 'generation-a', $queryHash, 0, 4);
    $expiredHashes = array_map(static fn (string $token): string => hash('sha256', $token), $tokens);
    $database->update('aculta_index_cursor')
      ->fields(['expires' => 999])
      ->condition('token_hash', $expiredHashes, 'IN')
      ->execute();

    $this->assertSame(2, $manager->prune(2));
    $this->assertSame(1, $manager->prune(2));
    $this->assertSame(0, $manager->prune(2));
    $this->assertSame(1, (int) $database->select('aculta_index_cursor')->countQuery()->execute()->fetchField());
    $this->assertSame(1, (int) $database->select('aculta_index_cursor')
      ->condition('token_hash', hash('sha256', $futureToken))
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Cursor cleanup refuses unbounded or empty batches.
   *
   * @dataProvider invalidCursorPruneLimitProvider
   */
  public function testCursorPruningRejectsInvalidLimits(int $limit): void {
    $manager = $this->container->get('aculta_index.cursor_manager');
    $this->expectException(\InvalidArgumentException::class);
    $manager->prune($limit);
  }

  /**
   * Provides invalid cursor cleanup limits.
   *
   * @return array<string, array{0: int}>
   *   Invalid limits.
   */
  public static function invalidCursorPruneLimitProvider(): array {
    return [
      'zero' => [0],
      'above maximum' => [5001],
    ];
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
   * A newly scheduled event cannot be completed by an older in-flight claim.
   */
  public function testWorkManagerKeepsNewerEventQueuedAfterStaleClaimCompletes(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $workId = $work->schedule('magazine', 'node', 'in-flight-1', 'pt_BR', 'project');
    $oldClaim = $work->claim($workId);
    $this->assertNotNull($oldClaim);

    // Model an entity event arriving after a worker claimed the old sequence.
    $newSequence = $work->schedule('magazine', 'node', 'in-flight-1', 'pt_BR', 'delete');
    $this->assertSame($oldClaim['sequence'] + 1, $newSequence);
    $this->assertFalse($work->complete($oldClaim['work_id'], $oldClaim['sequence']));
    $this->assertFalse($work->fail($oldClaim['work_id'], $oldClaim['sequence'], 'worker_failure'));

    $status = $work->getStatus('magazine', 'node', 'in-flight-1', 'pt_BR');
    $this->assertSame('queued', $status['status']);
    $this->assertSame('delete', $status['operation']);
    $this->assertSame($newSequence, $status['sequence']);

    $newClaim = $work->claim($workId);
    $this->assertSame($newSequence, $newClaim['sequence']);
    $this->assertSame('delete', $newClaim['operation']);
    $this->assertTrue($work->complete($workId, $newSequence));
  }

  /**
   * Checks approved retry count and exponential delay before dead-letter.
   */
  public function testWorkManagerRetriesFiveTimesThenMarksDead(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $work->schedule('image', 'node', 'retry-1', 'pt_BR');
    $database = $this->container->get('database');
    $delays = [30, 60, 120, 240];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
      $status = $work->getStatus('image', 'node', 'retry-1', 'pt_BR');
      $claim = $work->claim($status['work_id']);
      $this->assertNotNull($claim);
      $this->assertTrue($work->fail($claim['work_id'], $claim['sequence'], 'worker_failure'));
      $status = $work->getStatus('image', 'node', 'retry-1', 'pt_BR');
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

    $gate = $work->getActivationGateState(['image']);
    $this->assertFalse($gate['ready']);
    $this->assertSame(1, $gate['dead']);
    $dead = $work->listDead();
    $this->assertCount(1, $dead);
    $this->assertSame('worker_failure', $dead[0]['last_error_code']);
    $this->assertTrue($work->retryDead($dead[0]['work_id']));
    $status = $work->getStatus('image', 'node', 'retry-1', 'pt_BR');
    $this->assertSame('queued', $status['status']);
    $this->assertSame(0, $status['attempts']);
    $claim = $work->claim($status['work_id']);
    $this->assertTrue($work->complete($claim['work_id'], $claim['sequence']));
    $this->assertTrue($work->getActivationGateState(['image'])['ready']);
  }

  /**
   * A new source event supersedes dead work before an operator can retry it.
   */
  public function testNewEventReopensDeadLetterAndMakesOldRetryStale(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $work->schedule('image', 'node', 'dead-race', 'pt_BR', 'project');
    $status = $work->getStatus('image', 'node', 'dead-race', 'pt_BR');
    $this->assertNotNull($status);
    $workId = $status['work_id'];

    $this->container->get('database')->update('aculta_index_work')
      ->fields([
        'status' => 'dead',
        'attempts' => 5,
        'last_error_code' => 'worker_failure',
      ])
      ->condition('work_id', $workId)
      ->execute();

    $sequence = $work->schedule('image', 'node', 'dead-race', 'pt_BR', 'delete');

    $this->assertSame(2, $sequence);
    $this->assertFalse($work->retryDead($workId), 'An operator retry cannot replace a newer source event.');
    $latest = $work->getStatus('image', 'node', 'dead-race', 'pt_BR');
    $this->assertSame('queued', $latest['status']);
    $this->assertSame('delete', $latest['operation']);
    $this->assertSame(2, $latest['sequence']);
    $this->assertSame(0, $latest['attempts']);
    $this->assertSame('', $latest['last_error_code']);

    $claim = $work->claim($workId);
    $this->assertSame(2, $claim['sequence']);
    $this->assertSame('delete', $claim['operation']);
  }

  /**
   * An operator cannot retry work after its owning provider is disabled.
   */
  public function testDeadLetterRetryRequiresRegisteredProvider(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $work->schedule('image', 'media', 'disabled-owner-1', 'pt_BR');
    $status = $work->getStatus('image', 'media', 'disabled-owner-1', 'pt_BR');
    $this->container->get('database')->update('aculta_index_work')
      ->fields([
        'provider_id' => 'disabled_owner',
        'status' => 'dead',
        'attempts' => 5,
        'last_error_code' => 'worker_failure',
      ])
      ->condition('work_id', $status['work_id'])
      ->execute();

    $this->assertFalse($work->retryDead($status['work_id']));
    $dead = $work->getStatus('disabled_owner', 'media', 'disabled-owner-1', 'pt_BR');
    $this->assertSame('dead', $dead['status']);
    $this->assertSame(5, $dead['attempts']);
  }

  /**
   * Re-dispatches durable queued work after Core Queue insertion fails.
   */
  public function testWorkManagerRecoversQueuedWorkAfterQueueDispatchFailure(): void {
    $queue = new class() implements QueueInterface {

      /**
       * Whether item creation should fail.
       */
      public bool $fail = TRUE;

      /**
       * @var list<array{work_id: int}>
       */
      public array $items = [];

      /**
       * {@inheritdoc}
       */
      public function createItem($data) {
        if ($this->fail) {
          return FALSE;
        }
        if (!is_array($data) || !isset($data['work_id'])) {
          throw new \InvalidArgumentException('Unexpected process-test queue payload.');
        }
        $this->items[] = ['work_id' => (int) $data['work_id']];
        return count($this->items);
      }

      /**
       * {@inheritdoc}
       */
      public function numberOfItems() {
        return count($this->items);
      }

      /**
       * {@inheritdoc}
       */
      public function claimItem($lease_time = 3600) {
        return FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function deleteItem($item) {}

      /**
       * {@inheritdoc}
       */
      public function releaseItem($item) {
        return FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function createQueue() {}

      /**
       * {@inheritdoc}
       */
      public function deleteQueue() {}

    };
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($queue);
    $database = $this->container->get('database');
    $work = new ContentIndexWorkManager(
      $database,
      $queueFactory,
      $this->container->get('datetime.time'),
      $this->container->get('aculta_index.provider_registry'),
      new NullLogger(),
    );

    $this->assertSame(1, $work->schedule('magazine', 'node', 'queue-dispatch-failure', 'pt_BR'));
    $status = $work->getStatus('magazine', 'node', 'queue-dispatch-failure', 'pt_BR');
    $this->assertIsArray($status);
    $this->assertSame('queued', $status['status']);
    $this->assertSame(0, $queue->numberOfItems());
    $database->update('aculta_index_work')
      ->fields(['updated' => $this->container->get('datetime.time')->getRequestTime() - 301])
      ->condition('work_id', $status['work_id'])
      ->execute();

    $queue->fail = FALSE;
    $this->assertSame(1, $work->recover());
    $this->assertSame([['work_id' => $status['work_id']]], $queue->items);
    $claim = $work->claim($status['work_id']);
    $this->assertNotNull($claim);
    $this->assertSame(1, $claim['sequence']);
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
   * Keeps one rollback target and cleans older generations in batches.
   */
  public function testPromotionDiscardsOlderRollbackGeneration(): void {
    $manager = $this->container->get('aculta_index.generation_manager');
    $first = $manager->start();
    $manager->markBackfillCompleted($first, 0);
    $manager->markValidated($first);
    $manager->activate($first);

    $second = $manager->start();
    $manager->markBackfillCompleted($second, 0);
    $manager->markValidated($second);
    $manager->activate($second);

    $third = $manager->start();
    $manager->markBackfillCompleted($third, 0);
    $manager->markValidated($third);
    $manager->activate($third);

    $this->assertSame('discarding', $this->generationStatus($first));
    $this->assertSame('retained', $this->generationStatus($second));
    $this->assertSame('active', $this->generationStatus($third));
    $this->insertEntry($first, 'node', 'old-generation-entry', 'pt-br');
    $this->container->get('database')->insert('aculta_index_relation')
      ->fields([
        'generation_id' => $first,
        'provider_id' => 'fixture',
        'source_entity_type' => 'node',
        'source_entity_id' => 'old-generation-entry',
        'source_langcode' => 'pt-br',
        'relation_type' => 'related_entry',
        'target_entity_type' => 'node',
        'target_entity_id' => 'old-generation-target',
        'target_langcode' => 'pt-br',
        'relation_hash' => hash('sha256', 'old-generation-relation'),
        'updated' => 1,
      ])
      ->execute();

    $firstBatch = $manager->discardPendingBatch(1);
    $this->assertSame($first, $firstBatch['generation_id']);
    $this->assertSame(1, $firstBatch['entries_removed']);
    $this->assertSame(1, $firstBatch['relations_removed']);
    $this->assertTrue($firstBatch['complete']);
    $this->assertSame(0, (int) $this->container->get('database')->select('aculta_index_generation')
      ->condition('generation_id', $first)
      ->countQuery()
      ->execute()
      ->fetchField());
    $this->assertSame($third, $manager->getActiveGeneration());
    $this->assertSame('retained', $this->generationStatus($second));
    $this->assertNull($manager->discardPendingBatch()['generation_id']);
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
   * Tests the update hook creates the durable work table on older installs.
   */
  public function testUpdateHookCreatesWorkTable(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropTable('aculta_index_work');
    $this->assertFalse($schema->tableExists('aculta_index_work'));

    aculta_index_update_10003();
    aculta_index_update_10003();

    $this->assertTrue($schema->tableExists('aculta_index_work'));
    $this->assertTrue($schema->fieldExists('aculta_index_work', 'sequence'));
    $this->assertTrue($schema->indexExists('aculta_index_work', 'status_available'));
  }

  /**
   * Tests the update hook creates opaque cursor storage on older installs.
   */
  public function testUpdateHookCreatesCursorTable(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropTable('aculta_index_cursor');
    $this->assertFalse($schema->tableExists('aculta_index_cursor'));

    aculta_index_update_10005();
    aculta_index_update_10005();

    $this->assertTrue($schema->tableExists('aculta_index_cursor'));
    $this->assertTrue($schema->fieldExists('aculta_index_cursor', 'token_hash'));
    $this->assertTrue($schema->indexExists('aculta_index_cursor', 'owner_expiry'));
  }

  /**
   * Tests the cursor revision migration preserves existing cursor rows.
   */
  public function testUpdateHookAddsCursorRevision(): void {
    $database = $this->container->get('database');
    $database->insert('aculta_index_cursor')
      ->fields([
        'token_hash' => hash('sha256', 'legacy-cursor'),
        'uid' => 2,
        'generation_id' => 'legacy-cursor-generation',
        'query_hash' => hash('sha256', 'legacy-query'),
        'cursor_changed' => 10,
        'cursor_entry_id' => 42,
        'expires' => 200,
        'created' => 100,
        'updated' => 100,
      ])
      ->execute();
    $schema = $database->schema();
    $schema->dropField('aculta_index_cursor', 'cursor_revision');
    $this->assertFalse($schema->fieldExists('aculta_index_cursor', 'cursor_revision'));

    aculta_index_update_10006();
    aculta_index_update_10006();

    $this->assertTrue($schema->fieldExists('aculta_index_cursor', 'cursor_revision'));
    $cursor = $database->select('aculta_index_cursor', 'cursor')
      ->fields('cursor', ['cursor_changed', 'cursor_entry_id', 'cursor_revision'])
      ->condition('token_hash', hash('sha256', 'legacy-cursor'))
      ->execute()
      ->fetchAssoc();
    $this->assertSame('10', $cursor['cursor_changed']);
    $this->assertSame('42', $cursor['cursor_entry_id']);
    $this->assertSame('0', $cursor['cursor_revision']);
  }

  /**
   * Tests the intermediate changed-order index migration is idempotent.
   */
  public function testUpdateHookAddsChangedOrderIndex(): void {
    $schema = $this->container->get('database')->schema();
    $this->assertFalse($schema->indexExists('aculta_index_entry', 'gen_changed'));

    aculta_index_update_10007();
    aculta_index_update_10007();

    $this->assertTrue($schema->indexExists('aculta_index_entry', 'gen_changed'));
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
   * Tests the update hook adds the stale projection audit counter.
   */
  public function testUpdateHookAddsStaleProjectionAuditCounter(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropField('aculta_index_audit', 'stale_projection_count');
    $this->assertFalse($schema->fieldExists('aculta_index_audit', 'stale_projection_count'));

    aculta_index_update_10013();

    $this->assertTrue($schema->fieldExists('aculta_index_audit', 'stale_projection_count'));
  }

  /**
   * Tests the update hook adds the candidate cache revision.
   */
  public function testUpdateHookAddsCandidateCacheRevision(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropField('aculta_index_generation', 'candidate_revision');
    $this->assertFalse($schema->fieldExists('aculta_index_generation', 'candidate_revision'));

    aculta_index_update_10014();

    $this->assertTrue($schema->fieldExists('aculta_index_generation', 'candidate_revision'));
  }

  /**
   * Tests the update hook adds bounded source-reconciliation counters.
   */
  public function testUpdateHookAddsSourceReconciliationCounters(): void {
    $schema = $this->container->get('database')->schema();
    foreach (['missing_source_count', 'unverified_source_count'] as $field) {
      $schema->dropField('aculta_index_audit', $field);
      $this->assertFalse($schema->fieldExists('aculta_index_audit', $field));
    }

    aculta_index_update_10015();

    foreach (['missing_source_count', 'unverified_source_count'] as $field) {
      $this->assertTrue($schema->fieldExists('aculta_index_audit', $field));
    }
  }

  /**
   * Tests the update hook adds source-to-catalog keyset state and counter.
   */
  public function testUpdateHookAddsCatalogCoverageCheckpoint(): void {
    $schema = $this->container->get('database')->schema();
    foreach ([
      'source_provider_cursor',
      'source_entity_type_cursor',
      'source_entity_id_cursor',
      'missing_catalog_entry_count',
    ] as $field) {
      $schema->dropField('aculta_index_audit', $field);
      $this->assertFalse($schema->fieldExists('aculta_index_audit', $field));
    }

    aculta_index_update_10016();

    foreach ([
      'source_provider_cursor',
      'source_entity_type_cursor',
      'source_entity_id_cursor',
      'missing_catalog_entry_count',
    ] as $field) {
      $this->assertTrue($schema->fieldExists('aculta_index_audit', $field));
    }
  }

  /**
   * Tests the update hook adds a maximum-ID checkpoint for source audits.
   */
  public function testUpdateHookAddsSourceAuditMaximumId(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropField('aculta_index_audit', 'source_entity_max_id');
    $this->assertFalse($schema->fieldExists('aculta_index_audit', 'source_entity_max_id'));
    $this->container->get('database')->update('aculta_index_audit')
      ->fields(['status' => 'running'])
      ->condition('audit_id', 1)
      ->execute();

    aculta_index_update_10017();

    $this->assertTrue($schema->fieldExists('aculta_index_audit', 'source_entity_max_id'));
    $this->assertSame('idle', $this->container->get('database')->select('aculta_index_audit', 'audit')
      ->fields('audit', ['status'])
      ->condition('audit_id', 1)
      ->execute()
      ->fetchField(), 'A legacy in-progress source scan restarts without a captured maximum.');
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
   * Tests outgoing relation lookup indexes source identity and relation type.
   */
  public function testUpdateHookAddsRelationTypeToSourceIndex(): void {
    $schema = $this->container->get('database')->schema();
    $schema->dropIndex('aculta_index_relation', 'gen_source');
    $schema->addIndex(
      'aculta_index_relation',
      'gen_source',
      ['generation_id', 'source_entity_type', 'source_entity_id', 'source_langcode'],
      aculta_index_schema()['aculta_index_relation'],
    );

    aculta_index_update_10012();

    $this->assertTrue($schema->indexExists('aculta_index_relation', 'gen_source'));
    $this->assertSame([
      'generation_id',
      'source_entity_type',
      'source_entity_id',
      'source_langcode',
      'relation_type',
    ], aculta_index_schema()['aculta_index_relation']['indexes']['gen_source']);
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
      $this->container->get('aculta_index.candidate_cache'),
    );
    $first = $this->projection('Original', 10, 'activity-1');

    $this->assertTrue($writer->write($generation, 'magazine', $first, 1));
    $this->assertSame(1, $this->candidateRevision($generation));
    $this->assertFalse($writer->write($generation, 'magazine', $first, 1));
    $this->assertSame(1, $this->candidateRevision($generation));
    $this->assertSame(1, $this->entryCount($generation));
    $this->assertSame(1, $this->relationCount($generation));

    $secondSource = $this->projection('Outra publicação', 10, 'activity-1', '43');
    $this->assertTrue($writer->write($generation, 'magazine', $secondSource, 1));
    $this->assertSame(2, $this->candidateRevision($generation));
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
   * Checks batch writes commit atomically and bump the candidate revision once.
   */
  public function testWriterBatchIsAtomicAndAdvancesCandidateRevisionOnce(): void {
    $generation = $this->container->get('aculta_index.generation_manager')->start();
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('magazine');
    $provider->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);
    $provider->method('getProjectionVersion')->willReturn(1);
    $writer = new ContentIndexWriter(
      $this->container->get('database'),
      new ContentIndexProviderRegistry([$provider]),
      $this->container->get('datetime.time'),
      $this->container->get('aculta_index.candidate_cache'),
    );
    $batch = [
      $this->projection('Publicação 1', 10, 'activity-1', '42'),
      $this->projection('Publicação 2', 11, 'activity-2', '43'),
    ];

    $this->assertSame(2, $writer->writeBatch($generation, 'magazine', $batch, 1));
    $this->assertSame(1, $this->candidateRevision($generation));
    $this->assertSame(2, $this->entryCount($generation));
    $this->assertSame(0, $writer->writeBatch($generation, 'magazine', $batch, 1));
    $this->assertSame(1, $this->candidateRevision($generation));

    try {
      $writer->writeBatch($generation, 'magazine', [
        $this->projection('Publicação 3', 12, 'activity-3', '44'),
        $this->projection('Conflito na mesma sequência', 10, 'activity-1', '42'),
      ], 1);
      $this->fail('Expected the conflicting projection to roll back the complete batch.');
    }
    catch (\LogicException) {
      $this->assertSame(2, $this->entryCount($generation));
      $this->assertSame(1, $this->candidateRevision($generation));
    }
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
    $this->assertSame(0, $result['stale_projection_count']);
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
    $this->assertSame(0, $result['stale_projection_count']);
  }

  /**
   * A clean rebuild can validate while the old active projection is stale.
   */
  public function testIntegrityCheckEvaluatesTheRequestedGeneration(): void {
    $database = $this->container->get('database');
    foreach ([['old-generation', 'active'], ['new-generation', 'building']] as [$generationId, $status]) {
      $database->insert('aculta_index_generation')
        ->fields([
          'generation_id' => $generationId,
          'status' => $status,
          'validated' => (int) ($status === 'active'),
          'activated' => (int) ($status === 'active'),
        ])
        ->execute();
    }
    $database->update('aculta_index_state')
      ->fields(['active_generation' => 'old-generation', 'revision' => 1])
      ->condition('state_id', 1)
      ->execute();
    foreach ([['old-generation', 'old'], ['new-generation', 'new']] as [$generationId, $entityId]) {
      $this->insertEntry($generationId, 'media', $entityId, 'pt-br');
      $database->update('aculta_index_entry')
        ->fields([
          'provider_id' => 'image',
          'provider_version' => $entityId === 'old' ? 0 : 1,
        ])
        ->condition('generation_id', $generationId)
        ->condition('entity_id', $entityId)
        ->execute();
    }

    $checker = $this->container->get('aculta_index.integrity_checker');
    $activeHealth = $checker->check();
    $buildHealth = $checker->check('new-generation');

    $this->assertSame(1, $activeHealth['stale_projection_count']);
    $this->assertFalse($activeHealth['valid']);
    $this->assertSame('new-generation', $buildHealth['checked_generation']);
    $this->assertSame(0, $buildHealth['stale_projection_count']);
    $this->assertTrue($buildHealth['valid']);
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
    $this->insertEntry('audit-generation', 'media', '303', 'pt-br');
    $database->update('aculta_index_entry')
      ->fields(['provider_id' => 'image', 'bundle' => 'image', 'provider_version' => 0])
      ->condition('generation_id', 'audit-generation')
      ->condition('entity_id', '303')
      ->execute();
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
    $this->assertSame(0, (int) $state['stale_projection_count']);
    $this->assertSame(1, (int) $state['missing_source_count']);
    $this->assertSame(1, (int) $state['unverified_source_count']);
    $this->assertSame(0, (int) $state['orphan_relation_count']);
    $this->assertSame(1, (int) $state['relation_source_missing_count']);
    $repair = $database->select('aculta_index_work', 'work')
      ->fields('work', ['provider_id', 'entity_type', 'entity_id', 'langcode', 'operation', 'status'])
      ->condition('provider_id', 'image')
      ->condition('entity_id', '303')
      ->execute()
      ->fetchAssoc();
    $this->assertSame([
      'provider_id' => 'image',
      'entity_type' => 'media',
      'entity_id' => '303',
      'langcode' => 'pt-br',
      'operation' => 'delete',
      'status' => 'queued',
    ], $repair);
    $this->assertSame(1, (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', 'audit-generation')
      ->condition('provider_id', 'fixture')
      ->condition('entity_id', '301')
      ->countQuery()
      ->execute()
      ->fetchField(), 'Entries without a registered owner remain intact.');
    $this->assertSame(0, (int) $database->select('aculta_index_work', 'work')
      ->condition('provider_id', 'fixture')
      ->condition('entity_id', '301')
      ->countQuery()
      ->execute()
      ->fetchField(), 'An unverified entry must not get a destructive repair job.');
  }

  /**
   * Deletes an orphan only after its owner provider is registered again.
   */
  public function testAuditCleansOwnerProjectionAfterProviderReactivation(): void {
    $database = $this->container->get('database');
    $generationId = 'owner-reactivation-generation';
    $database->insert('aculta_index_generation')
      ->fields([
        'generation_id' => $generationId,
        'status' => 'active',
        'validated' => 1,
        'activated' => 1,
      ])
      ->execute();
    $database->update('aculta_index_state')
      ->fields(['active_generation' => $generationId, 'revision' => 1])
      ->condition('state_id', 1)
      ->execute();
    $this->insertEntry($generationId, 'media', '909', 'pt-br');
    $database->update('aculta_index_entry')
      ->fields([
        'provider_id' => 'image',
        'bundle' => 'image',
        'provider_version' => 1,
      ])
      ->condition('generation_id', $generationId)
      ->condition('entity_id', '909')
      ->execute();

    $registeredProviders = $this->container->get('aculta_index.provider_registry');
    self::assertTrue($registeredProviders->has('image'));
    $entityTypeManager = $this->container->get('entity_type.manager');
    $workManager = $this->container->get('aculta_index.work_manager');
    $emptyRegistry = new ContentIndexProviderRegistry();
    $disabledOwnerChecker = new ContentIndexIntegrityChecker(
      $database,
      $this->container->get('datetime.time'),
      $emptyRegistry,
      $workManager,
      new ContentIndexEntityEnumerator($emptyRegistry, $entityTypeManager),
      $entityTypeManager,
      $this->container->get('aculta_index.writer'),
      $this->container->get('logger.channel.aculta_index'),
    );
    $finishAudit = static function (ContentIndexIntegrityChecker $checker): array {
      $state = $checker->startAudit();
      while ($state['status'] === 'running') {
        $state = $checker->processAuditBatch(10);
      }
      return $state;
    };

    $disabledAudit = $finishAudit($disabledOwnerChecker);
    self::assertSame(1, (int) $disabledAudit['unverified_source_count']);
    self::assertSame(0, (int) $disabledAudit['missing_source_count']);
    self::assertSame(1, (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'image')
      ->condition('entity_id', '909')
      ->countQuery()
      ->execute()
      ->fetchField(), 'An unavailable owner cannot authorize cleanup.');
    self::assertNull($workManager->getStatus('image', 'media', '909', 'pt-br'));

    $ownerReadyChecker = new ContentIndexIntegrityChecker(
      $database,
      $this->container->get('datetime.time'),
      $registeredProviders,
      $workManager,
      new ContentIndexEntityEnumerator($registeredProviders, $entityTypeManager),
      $entityTypeManager,
      $this->container->get('aculta_index.writer'),
      $this->container->get('logger.channel.aculta_index'),
    );
    $ownerReadyAudit = $finishAudit($ownerReadyChecker);
    self::assertSame(1, (int) $ownerReadyAudit['missing_source_count']);
    self::assertSame(0, (int) $ownerReadyAudit['unverified_source_count']);
    $repair = $workManager->getStatus('image', 'media', '909', 'pt-br');
    self::assertIsArray($repair);
    self::assertSame('queued', $repair['status']);

    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    $worker->processItem(['work_id' => $repair['work_id']]);
    self::assertSame(0, (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'image')
      ->condition('entity_id', '909')
      ->countQuery()
      ->execute()
      ->fetchField(), 'Reactivated owner verification lets the worker remove only the derived row.');
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
   * Discards only incomplete generations and deletes their rows in batches.
   */
  public function testDiscardBuildingGenerationIsBoundedAndProtectsActiveGeneration(): void {
    $database = $this->container->get('database');
    foreach ([['gen-building', 'building'], ['gen-active', 'active']] as [$id, $status]) {
      $database->insert('aculta_index_generation')->fields([
        'generation_id' => $id,
        'status' => $status,
        'provider_fingerprint' => '',
        'created' => 1,
        'updated' => 1,
        'validated' => $status === 'active' ? 1 : 0,
      ])->execute();
    }
    $database->update('aculta_index_state')
      ->fields(['active_generation' => 'gen-active', 'revision' => 1])
      ->condition('state_id', 1)
      ->execute();

    $this->insertEntry('gen-building', 'node', 'discard-1', 'pt-br');
    $this->insertEntry('gen-building', 'node', 'discard-2', 'pt-br');
    $this->insertEntry('gen-active', 'node', 'keep-active', 'pt-br');
    foreach (['discard-1', 'discard-2'] as $entityId) {
      $database->insert('aculta_index_relation')->fields([
        'generation_id' => 'gen-building',
        'provider_id' => 'fixture',
        'source_entity_type' => 'node',
        'source_entity_id' => $entityId,
        'source_langcode' => 'pt-br',
        'relation_type' => 'related_entry',
        'target_entity_type' => 'node',
        'target_entity_id' => 'target-' . $entityId,
        'target_langcode' => 'pt-br',
        'relation_hash' => hash('sha256', $entityId),
        'updated' => 1,
      ])->execute();
    }

    $manager = $this->container->get('aculta_index.generation_manager');
    try {
      $manager->discardBuilding('gen-active');
      $this->fail('The active generation must not be eligible for discard.');
    }
    catch (\InvalidArgumentException) {
      $this->assertSame(1, $this->entryCount('gen-active'));
    }

    $manager->discardBuilding('gen-building');
    $this->assertNotContains('gen-building', $manager->getWritableGenerations());
    $first = $manager->discardBatch('gen-building', 1);
    $this->assertSame(1, $first['entries_removed']);
    $this->assertSame(1, $first['relations_removed']);
    $this->assertFalse($first['complete']);
    $second = $manager->discardBatch('gen-building', 1);
    $this->assertSame(1, $second['entries_removed']);
    $this->assertSame(1, $second['relations_removed']);
    $this->assertTrue($second['complete']);
    $this->assertSame(0, $this->entryCount('gen-building'));
    $this->assertSame(0, $this->relationCount('gen-building'));
    $this->assertSame('gen-active', $manager->getActiveGeneration());
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
   * Reads a generation's candidate cache revision.
   */
  private function candidateRevision(string $generationId): int {
    return (int) $this->container->get('database')->select('aculta_index_generation', 'generation')
      ->fields('generation', ['candidate_revision'])
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
