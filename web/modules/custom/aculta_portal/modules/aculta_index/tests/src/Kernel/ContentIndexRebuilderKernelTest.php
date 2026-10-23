<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_index\Drush\Commands\ContentIndexQueueCommand;
use Drupal\aculta_index\Drush\Commands\ContentIndexRebuildCommand;
use Drupal\aculta_index\Drush\Commands\ContentIndexAuditCommand;
use Drupal\aculta_index\Statistics\ContentIndexStatisticsBridge;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\UserSession;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies provider-backed rebuild batches against real node storage.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexRebuilderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
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
    // Fixture providers do not use Domain-aware access policies.
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc} */
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
    $this->installConfig(['node', 'user']);
    $this->config('user.role.anonymous')->set('permissions', ['access content'])->save();
    aculta_index_install();
    NodeType::create(['type' => 'sample_content', 'name' => 'Article'])->save();
  }

  /**
   * Statistics remains an optional dependency of the Index bridge.
   */
  public function testStatisticsBridgeResolvesWithoutStatisticsModule(): void {
    $bridge = $this->container->get('aculta_index.statistics_bridge');
    $this->assertInstanceOf(ContentIndexStatisticsBridge::class, $bridge);
    $result = $bridge->aggregateForQuery([], 'wiki.entries.read', 0, 0, new AnonymousUserSession());
    $this->assertFalse($result->available);
    $this->assertSame([], $result->totals);
  }

  /**
   * Both query APIs enforce the fixed candidate limit before doing work.
   */
  public function testContentAndRelationQueriesRejectOutOfRangeLimits(): void {
    $account = new AnonymousUserSession();
    $query = $this->container->get('aculta_index.query');
    foreach ([0, 101] as $limit) {
      try {
        $query->query([], $account, NULL, $limit);
        $this->fail(sprintf('The content query must reject candidate limit %d.', $limit));
      }
      catch (\InvalidArgumentException $exception) {
        $this->assertSame('Content Index candidate limit must be between 1 and 100.', $exception->getMessage());
      }
    }

    $relationQuery = $this->container->get('aculta_index.relation_query');
    foreach ([0, 101] as $limit) {
      try {
        $relationQuery->queryOutgoing('node', '1', 'en', 'related_item', $account, NULL, $limit);
        $this->fail(sprintf('The relation query must reject candidate limit %d.', $limit));
      }
      catch (\InvalidArgumentException $exception) {
        $this->assertSame('Content Index relation candidate limit must be between 1 and 100.', $exception->getMessage());
      }
    }
  }

  /**
   * Checks keyset batches resume and persist projections without activation.
   */
  public function testBackfillResumesAndOnlyWritesBuildingGeneration(): void {
    Node::create(['type' => 'sample_content', 'title' => 'Content one', 'status' => 1])->save();
    Node::create(['type' => 'sample_content', 'title' => 'Content two', 'status' => 1])->save();
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();

    $first = $rebuilder->processNextBatch($generationId, 1);
    $second = $rebuilder->processNextBatch($generationId, 1);
    $last = $rebuilder->processNextBatch($generationId, 1);

    $this->assertFalse($first['complete']);
    $this->assertFalse($second['complete']);
    $this->assertTrue($last['complete']);
    $this->assertSame(1, $first['projected']);
    $this->assertSame(1, $second['projected']);
    $this->assertSame(0, $last['projected']);
    $this->assertSame(2, (int) $this->container->get('database')->select('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->countQuery()
      ->execute()
      ->fetchField());
    $this->assertSame('', $this->container->get('database')->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchField());

    $validation = $rebuilder->validate($generationId);
    $this->assertTrue($validation['valid']);
    $generations = $this->container->get('aculta_index.generation_manager');
    try {
      $generations->activate($generationId);
      $this->fail('Expected queued source work to block activation.');
    }
    catch (\RuntimeException) {
      $this->assertNull($generations->getActiveGeneration());
    }
    $this->completeBackfilledSourceWork();
    $generations->activate($generationId);
    $this->assertSame($generationId, $this->container->get('aculta_index.generation_manager')->getActiveGeneration());
  }

  /**
   * Refuses to resume a generation with a changed provider inventory.
   */
  public function testBackfillRejectsProviderInventoryDrift(): void {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $this->container->get('database')->update('aculta_index_generation')
      ->fields(['provider_fingerprint' => str_repeat('0', 64)])
      ->condition('generation_id', $generationId)
      ->execute();

    $this->expectException(\UnexpectedValueException::class);
    $rebuilder->processNextBatch($generationId);
  }

  /**
   * Rejects activation if an installed provider changes after validation.
   */
  public function testActivationRejectsProviderInventoryDriftAfterValidation(): void {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $rebuilder->processNextBatch($generationId);
    $rebuilder->validate($generationId);
    $this->container->get('database')->update('aculta_index_generation')
      ->fields(['provider_fingerprint' => str_repeat('0', 64)])
      ->condition('generation_id', $generationId)
      ->execute();

    $this->expectException(\RuntimeException::class);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
  }

  /**
   * Runs the explicit rebuild lifecycle through its Drush command class.
   */
  public function testDrushCommandsRunCompleteEmptyRebuildLifecycle(): void {
    $queueCommand = ContentIndexQueueCommand::create($this->container);
    $queueOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $queueCommand->run(new ArrayInput(['action' => 'status']), $queueOutput));
    $this->assertStringContainsString('activation_ready=yes', $queueOutput->fetch());

    $command = ContentIndexRebuildCommand::create($this->container);
    $output = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput(['action' => 'start']), $output));
    $generationId = trim($output->fetch());
    $this->assertNotSame('', $generationId);

    $batchOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput([
      'action' => 'batch',
      'generation-id' => $generationId,
    ]), $batchOutput));
    $this->assertStringContainsString('"complete":true', $batchOutput->fetch());

    $validateOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput([
      'action' => 'validate',
      'generation-id' => $generationId,
    ]), $validateOutput));
    $this->assertStringContainsString('"validated":true', $validateOutput->fetch());

    $activateOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput([
      'action' => 'activate',
      'generation-id' => $generationId,
    ]), $activateOutput));
    $this->assertSame($generationId, $this->container->get('aculta_index.generation_manager')->getActiveGeneration());
  }

  /**
   * Lists dead letters and retries one through the operator command.
   */
  public function testQueueCommandListsAndRetriesDeadLetter(): void {
    $work = $this->container->get('aculta_index.work_manager');
    $work->schedule('test_sample_content', 'node', 'dead-item', 'en');
    $status = $work->getStatus('test_sample_content', 'node', 'dead-item', 'en');
    $this->assertNotNull($status);
    $workId = $status['work_id'];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
      $claim = $work->claim($status['work_id']);
      $this->assertNotNull($claim);
      $this->assertTrue($work->fail($claim['work_id'], $claim['sequence'], 'synthetic_error'));
      if ($attempt < 5) {
        $this->container->get('database')->update('aculta_index_work')
          ->fields(['available_at' => 0])
          ->condition('work_id', $workId)
          ->execute();
        $this->assertSame(1, $work->recover());
      }
    }

    $command = ContentIndexQueueCommand::create($this->container);
    $deadOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput([
      'action' => 'dead',
      '--limit' => 10,
    ]), $deadOutput));
    $this->assertStringContainsString(
      "test_sample_content\tnode\tdead-item\ten\t5\tsynthetic_error",
      $deadOutput->fetch(),
    );

    $invalidLimitOutput = new BufferedOutput();
    $this->assertSame(Command::FAILURE, $command->run(new ArrayInput([
      'action' => 'dead',
      '--limit' => 101,
    ]), $invalidLimitOutput));
    $this->assertStringContainsString('--limit precisa estar entre 1 e 100', $invalidLimitOutput->fetch());

    $retryOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput([
      'action' => 'retry',
      'work-id' => (string) $workId,
    ]), $retryOutput));
    $this->assertSame('queued', $work->getStatus('test_sample_content', 'node', 'dead-item', 'en')['status']);
    $this->assertStringContainsString('Job reenfileirado', $retryOutput->fetch());
  }

  /**
   * A dead-letter cannot be retried after its owner provider is disabled.
   */
  public function testDeadLetterRetryRequiresRegisteredProvider(): void {
    $database = $this->container->get('database');
    $workId = (int) $database->insert('aculta_index_work')
      ->fields([
        'provider_id' => 'disabled_owner',
        'entity_type' => 'node',
        'entity_id' => 'retired-source',
        'langcode' => 'en',
        'sequence' => 5,
        'operation' => 'project',
        'status' => 'dead',
        'attempts' => 5,
        'available_at' => 0,
        'last_error_code' => 'synthetic_error',
        'created' => 1,
        'updated' => 1,
      ])
      ->execute();

    $work = $this->container->get('aculta_index.work_manager');
    $this->assertFalse($work->retryDead($workId));
    $this->assertSame('dead', $work->getStatus('disabled_owner', 'node', 'retired-source', 'en')['status']);
  }

  /**
   * Tests the audit command reports and advances a bounded checkpoint.
   */
  public function testAuditDrushCommandRunsBoundedCycle(): void {
    $command = ContentIndexAuditCommand::create($this->container);
    $startOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput(['action' => 'start']), $startOutput));
    $this->assertStringContainsString('"status":"running"', $startOutput->fetch());

    $batchOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput(['action' => 'batch']), $batchOutput));
    $this->assertStringContainsString('"phase":"relations"', $batchOutput->fetch());

    $statusOutput = new BufferedOutput();
    $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput(['action' => 'status']), $statusOutput));
    $this->assertStringContainsString('"status":"running"', $statusOutput->fetch());
  }

  /**
   * Requeues an existing source whose current projection differs from storage.
   */
  public function testIncrementalAuditRequeuesDivergedExistingProjection(): void {
    Node::create(['type' => 'sample_content', 'title' => 'Current source', 'status' => 1])->save();
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 10)['complete']) {
    }
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $database = $this->container->get('database');
    $database->update('aculta_index_entry')
      ->fields(['projection_hash' => str_repeat('0', 64)])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'test_sample_content')
      ->execute();

    $checker = $this->container->get('aculta_index.integrity_checker');
    $checker->startAudit();
    $state = $checker->processAuditBatch(10);
    $this->assertSame(1, (int) $state['stale_projection_count']);
    $this->assertSame(0, (int) $state['missing_source_count']);
    $this->assertSame(0, (int) $state['unverified_source_count']);

    $work = $database->select('aculta_index_work', 'work')
      ->fields('work', ['operation', 'status'])
      ->condition('provider_id', 'test_sample_content')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '1')
      ->condition('langcode', 'en')
      ->execute()
      ->fetchAssoc();
    $this->assertSame(['operation' => 'project', 'status' => 'queued'], $work);
  }

  /**
   * Finds a provider-owned source entity missing from the active catalog.
   */
  public function testIncrementalAuditFindsSourceMissingFromCatalog(): void {
    Node::create(['type' => 'sample_content', 'title' => 'Uncatalogued source', 'status' => 1])->save();
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 10)['complete']) {
    }
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $database = $this->container->get('database');
    $database->delete('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'test_sample_content')
      ->condition('entity_id', '1')
      ->execute();

    $checker = $this->container->get('aculta_index.integrity_checker');
    $checker->startAudit();
    $state = $checker->processAuditBatch(10);
    $this->assertSame('relations', $state['phase']);
    $state = $checker->processAuditBatch(10);
    $this->assertSame('sources', $state['phase']);
    $state = $checker->processAuditBatch(10);
    $this->assertSame('complete', $state['status']);
    $this->assertSame(1, (int) $state['missing_catalog_entry_count']);

    $work = $database->select('aculta_index_work', 'work')
      ->fields('work', ['operation', 'status'])
      ->condition('provider_id', 'test_sample_content')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '1')
      ->condition('langcode', 'en')
      ->execute()
      ->fetchAssoc();
    $this->assertSame(['operation' => 'project', 'status' => 'queued'], $work);
  }

  /**
   * Checks query pagination uses an opaque persisted cursor and access checks.
   */
  public function testQueryUsesOpaqueCursorAndReturnsAuthorizedEntities(): void {
    foreach (['Content one', 'Content two', 'Private content'] as $index => $title) {
      $node = Node::create([
        'type' => 'sample_content',
        'title' => $title,
        'created' => 1000,
        'changed' => 1000,
      ]);
      if ($index < 2) {
        $node->setPublished();
      }
      else {
        $node->setUnpublished();
      }
      $node->save();
    }
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 2)['complete']) {
    }
    $generations = $this->container->get('aculta_index.generation_manager');
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $generations->activate($generationId);

    $query = $this->container->get('aculta_index.query');
    $account = new AnonymousUserSession();
    $privateNode = $this->container->get('entity_type.manager')->getStorage('node')->load(3);
    $this->assertFalse($privateNode->isPublished());
    $this->assertFalse($privateNode->access('view', $account));
    $authorized = $query->query([], $account, NULL, 4);
    $this->assertCount(2, $authorized->entities);
    $labels = array_map(static fn ($entity): string => (string) $entity->label(), $authorized->entities);
    $this->assertNotContains('Private content', $labels);
    $firstPage = $query->query(['published' => TRUE], $account, NULL, 2);
    $this->assertCount(2, $firstPage->entities);
    $this->assertNotNull($firstPage->nextCursor);
    $storedHash = $this->container->get('database')->select('aculta_index_cursor', 'cursor')
      ->fields('cursor', ['token_hash'])
      ->execute()
      ->fetchField();
    $this->assertNotSame($firstPage->nextCursor, $storedHash);
    $this->assertSame(0, $firstPage->cacheability->getCacheMaxAge());

    try {
      $query->query(['bundle' => 'sample_content'], $account, $firstPage->nextCursor, 2);
      $this->fail('Expected a cursor bound to different filters to be rejected.');
    }
    catch (\InvalidArgumentException) {
      $this->assertSame(1, (int) $this->container->get('database')->select('aculta_index_cursor')->countQuery()->execute()->fetchField());
    }
    try {
      $otherAccount = new UserSession(['uid' => 24, 'roles' => ['anonymous']]);
      $query->query(['published' => TRUE], $otherAccount, $firstPage->nextCursor, 2);
      $this->fail('Expected a cursor bound to another account to be rejected.');
    }
    catch (\InvalidArgumentException) {
      $this->assertSame(1, (int) $this->container->get('database')->select('aculta_index_cursor')->countQuery()->execute()->fetchField());
    }

    $secondPage = $query->query(['published' => TRUE], $account, $firstPage->nextCursor, 2);
    $this->assertCount(0, $secondPage->entities);
    $this->assertNull($secondPage->nextCursor);
    $this->assertSame(0, (int) $this->container->get('database')->select('aculta_index_cursor')->countQuery()->execute()->fetchField());

    $publicNode = $this->container->get('entity_type.manager')->getStorage('node')->load(1);
    $publicNode->setUnpublished();
    $publicNode->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('node')->resetCache();
    $this->assertFalse($publicNode->isPublished());
    $this->assertFalse($publicNode->access('view', $account));
    $afterRevocation = $query->query(['published' => TRUE], $account, NULL, 2);
    $this->assertCount(1, $afterRevocation->entities);
    $this->assertSame('Content two', (string) $afterRevocation->entities[0]->label());
  }

  /**
   * Candidate pages are invalidated when a projection is removed.
   */
  public function testCandidateCacheInvalidatesAfterProjectionRemoval(): void {
    foreach (['First identity', 'Second identity'] as $title) {
      Node::create([
        'type' => 'sample_content',
        'title' => $title,
        'status' => 1,
        'created' => 1000,
        'changed' => 1000,
      ])->save();
    }
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 10)['complete']) {
    }
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $query = $this->container->get('aculta_index.query');
    $account = new AnonymousUserSession();
    $firstPage = $query->query([], $account, NULL, 1);
    $this->assertSame('Second identity', (string) $firstPage->entities[0]->label());

    $langcode = (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['langcode'])
      ->condition('generation_id', $generationId)
      ->condition('entity_id', '2')
      ->execute()
      ->fetchField();
    $this->container->get('aculta_index.writer')->remove(
      $generationId,
      'test_sample_content',
      'node',
      '2',
      $langcode,
      1,
    );

    $afterRemoval = $query->query([], $account, NULL, 1);
    $this->assertCount(1, $afterRemoval->entities);
    $this->assertSame('First identity', (string) $afterRemoval->entities[0]->label());
  }

  /**
   * Rechecks Entity Access before a queued revocation is applied.
   */
  public function testCursorPageHidesEntityMadePrivateWhileCatalogIsStale(): void {
    foreach (['First content', 'Second content'] as $title) {
      Node::create([
        'type' => 'sample_content',
        'title' => $title,
        'status' => 1,
        'created' => 1000,
        'changed' => 1000,
      ])->save();
    }
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 10)['complete']) {
    }
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $account = new AnonymousUserSession();
    $query = $this->container->get('aculta_index.query');
    $firstPage = $query->query(['published' => TRUE], $account, NULL, 1);
    $this->assertCount(1, $firstPage->entities);
    $this->assertNotNull($firstPage->nextCursor);

    $privateCandidate = $this->container->get('entity_type.manager')->getStorage('node')->load(1);
    $privateCandidate->setUnpublished();
    $privateCandidate->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('node')->resetCache();
    $indexedPublished = $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['published'])
      ->condition('generation_id', $generationId)
      ->condition('entity_id', '1')
      ->execute()
      ->fetchField();
    $this->assertSame('1', $indexedPublished, 'The test exercises a stale public catalog row before its worker runs.');

    $nextPage = $query->query(['published' => TRUE], $account, $firstPage->nextCursor, 1);
    $this->assertSame([], $nextPage->entities);
    $this->assertSame(0, $nextPage->cacheability->getCacheMaxAge());
  }

  /**
   * Keeps a page position stable when a later candidate changes timestamp.
   */
  public function testCursorUsesImmutableEntryIdWhenEntityChanges(): void {
    foreach (['Older identity', 'Newer identity'] as $title) {
      Node::create([
        'type' => 'sample_content',
        'title' => $title,
        'status' => 1,
        'created' => 1000,
        'changed' => 1000,
      ])->save();
    }
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 10)['complete']) {
    }
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $query = $this->container->get('aculta_index.query');
    $account = new AnonymousUserSession();
    $firstPage = $query->query([], $account, NULL, 1);
    $this->assertSame('Newer identity', (string) $firstPage->entities[0]->label());
    $this->assertNotNull($firstPage->nextCursor);

    // A changed-time sort would move this item before the cursor and omit it.
    $olderNode = $this->container->get('entity_type.manager')->getStorage('node')->load(1);
    $olderNode->setTitle('Changed after page one');
    $olderNode->save();

    $secondPage = $query->query([], $account, $firstPage->nextCursor, 1);
    $this->assertCount(1, $secondPage->entities);
    $this->assertSame('Changed after page one', (string) $secondPage->entities[0]->label());
  }

  /**
   * Tests outgoing relation pagination and access for source and targets.
   */
  public function testOutgoingRelationQueryUsesDefaultLanguageAndEntityAccess(): void {
    $source = Node::create(['type' => 'sample_content', 'title' => 'Source content', 'status' => 1]);
    $publicTarget = Node::create(['type' => 'sample_content', 'title' => 'Public target', 'status' => 1]);
    $staleTarget = Node::create(['type' => 'sample_content', 'title' => 'Stale target', 'status' => 1]);
    $privateTarget = Node::create(['type' => 'sample_content', 'title' => 'Private target', 'status' => 0]);
    $privateSource = Node::create(['type' => 'sample_content', 'title' => 'Private source', 'status' => 0]);
    foreach ([$source, $publicTarget, $staleTarget, $privateTarget, $privateSource] as $node) {
      $node->save();
    }

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    while (!$rebuilder->processNextBatch($generationId, 10)['complete']) {
    }
    $rebuilder->validate($generationId);
    $this->completeBackfilledSourceWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $database = $this->container->get('database');
    foreach ([
      [$source, $publicTarget],
      [$source, $staleTarget],
      [$source, $privateTarget],
      [$privateSource, $publicTarget],
    ] as [$from, $to]) {
      $database->insert('aculta_index_relation')
        ->fields([
          'generation_id' => $generationId,
          'provider_id' => 'test_sample_content',
          'source_entity_type' => 'node',
          'source_entity_id' => (string) $from->id(),
          'source_langcode' => $from->language()->getId(),
          'relation_type' => 'related_item',
          'target_entity_type' => 'node',
          'target_entity_id' => (string) $to->id(),
          'target_langcode' => 'und',
          'relation_hash' => hash('sha256', $from->id() . ':' . $to->id()),
          'updated' => 1,
        ])
        ->execute();
    }
    // The same resolved translation can be reached by both an entity-level
    // (und) edge and an explicit default-language edge. Results are unique by
    // target identity rather than relation row.
    $database->insert('aculta_index_relation')
      ->fields([
        'generation_id' => $generationId,
        'provider_id' => 'test_sample_content',
        'source_entity_type' => 'node',
        'source_entity_id' => (string) $source->id(),
        'source_langcode' => $source->language()->getId(),
        'relation_type' => 'related_item',
        'target_entity_type' => 'node',
        'target_entity_id' => (string) $publicTarget->id(),
        'target_langcode' => $this->container->get('language_manager')->getDefaultLanguage()->getId(),
        'relation_hash' => hash('sha256', 'same-target-explicit-language'),
        'updated' => 1,
      ])
      ->execute();
    // Old projections are excluded from relation targets until rewritten.
    $database->update('aculta_index_entry')
      ->fields(['provider_version' => 0])
      ->condition('entity_id', (string) $staleTarget->id())
      ->execute();

    $account = new AnonymousUserSession();
    $query = $this->container->get('aculta_index.relation_query');
    $page = $query->queryOutgoing('node', (string) $source->id(), $source->language()->getId(), 'related_item', $account, NULL, 1);
    $targets = $page->entities;
    $pages = 1;
    while ($page->nextCursor !== NULL) {
      $page = $query->queryOutgoing('node', (string) $source->id(), $source->language()->getId(), 'related_item', $account, $page->nextCursor, 1);
      $targets = array_merge($targets, $page->entities);
      $pages++;
      $this->assertLessThanOrEqual(5, $pages, 'The relation cursor terminates within the candidate set.');
    }
    $this->assertCount(1, $targets, 'A target reached by both und and explicit default-language edges appears once.');
    $this->assertSame('Public target', (string) $targets[0]->label());
    $this->assertSame($this->container->get('language_manager')->getDefaultLanguage()->getId(), $targets[0]->language()->getId());

    // A relation edge is unusable if its source projection belongs to an old
    // provider contract, even when the source entity itself remains viewable.
    $database->update('aculta_index_entry')
      ->fields(['provider_version' => 0])
      ->condition('generation_id', $generationId)
      ->condition('entity_type', 'node')
      ->condition('entity_id', (string) $source->id())
      ->execute();
    $staleSource = $query->queryOutgoing('node', (string) $source->id(), $source->language()->getId(), 'related_item', $account);
    $this->assertSame([], $staleSource->entities, 'Relations from an obsolete source projection are not exposed.');
    $this->assertNull($staleSource->nextCursor);

    $deniedSource = $query->queryOutgoing('node', (string) $privateSource->id(), $privateSource->language()->getId(), 'related_item', $account);
    $this->assertSame([], $deniedSource->entities);
    $this->assertNull($deniedSource->nextCursor);
    $this->assertSame(0, $deniedSource->cacheability->getCacheMaxAge());
  }

  /**
   * Marks pre-build jobs complete after this test's backfill read their source.
   */
  private function completeBackfilledSourceWork(): void {
    $database = $this->container->get('database');
    $work = $this->container->get('aculta_index.work_manager');
    $workIds = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'test_sample_content')
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    foreach ($workIds as $workId) {
      $claim = $work->claim((int) $workId);
      if ($claim !== NULL) {
        $work->complete($claim['work_id'], $claim['sequence']);
      }
    }
  }

}
