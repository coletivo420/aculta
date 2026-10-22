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
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
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
   * Checks keyset batches resume and persist projections without activation.
   */
  public function testBackfillResumesAndOnlyWritesBuildingGeneration(): void {
    Node::create(['type' => 'article', 'title' => 'Article one', 'status' => 1])->save();
    Node::create(['type' => 'article', 'title' => 'Article two', 'status' => 1])->save();
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
   * Checks query pagination uses an opaque persisted cursor and access checks.
   */
  public function testQueryUsesOpaqueCursorAndReturnsAuthorizedEntities(): void {
    foreach (['Article one', 'Article two', 'Private article'] as $index => $title) {
      $node = Node::create([
        'type' => 'article',
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
    $this->assertNotContains('Private article', $labels);
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
      $query->query(['bundle' => 'article'], $account, $firstPage->nextCursor, 2);
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
    $this->assertSame('Article two', (string) $afterRevocation->entities[0]->label());
  }

  /**
   * Tests outgoing relation pagination and access for source and targets.
   */
  public function testOutgoingRelationQueryUsesDefaultLanguageAndEntityAccess(): void {
    $source = Node::create(['type' => 'article', 'title' => 'Source article', 'status' => 1]);
    $publicTarget = Node::create(['type' => 'article', 'title' => 'Public target', 'status' => 1]);
    $privateTarget = Node::create(['type' => 'article', 'title' => 'Private target', 'status' => 0]);
    $privateSource = Node::create(['type' => 'article', 'title' => 'Private source', 'status' => 0]);
    foreach ([$source, $publicTarget, $privateTarget, $privateSource] as $node) {
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
      [$source, $privateTarget],
      [$privateSource, $publicTarget],
    ] as [$from, $to]) {
      $database->insert('aculta_index_relation')
        ->fields([
          'generation_id' => $generationId,
          'provider_id' => 'test_article',
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

    $account = new AnonymousUserSession();
    $query = $this->container->get('aculta_index.relation_query');
    $firstPage = $query->queryOutgoing('node', (string) $source->id(), $source->language()->getId(), 'related_item', $account, NULL, 1);
    $this->assertCount(0, $firstPage->entities);
    $this->assertNotNull($firstPage->nextCursor);

    $secondPage = $query->queryOutgoing('node', (string) $source->id(), $source->language()->getId(), 'related_item', $account, $firstPage->nextCursor, 1);
    $this->assertCount(1, $secondPage->entities);
    $this->assertSame('Public target', (string) $secondPage->entities[0]->label());
    $this->assertSame($this->container->get('language_manager')->getDefaultLanguage()->getId(), $secondPage->entities[0]->language()->getId());
    $this->assertNotNull($secondPage->nextCursor);

    $endPage = $query->queryOutgoing('node', (string) $source->id(), $source->language()->getId(), 'related_item', $account, $secondPage->nextCursor, 1);
    $this->assertSame([], $endPage->entities);
    $this->assertNull($endPage->nextCursor);

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
      ->condition('provider_id', 'test_article')
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
