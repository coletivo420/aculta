<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the public Content Index query contract on a real Kernel database.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexQueryKernelTest extends KernelTestBase {

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
    $this->installConfig(['node', 'user', 'system']);
    aculta_index_install();
    NodeType::create(['type' => 'sample_content', 'name' => 'Sample content'])->save();

    $authenticated = Role::load(Role::AUTHENTICATED_ID);
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
  }

  /**
   * Query cursors are opaque, keyset-stable and access is revalidated per page.
   */
  public function testQueryUsesStableCursorAndCurrentEntityAccess(): void {
    // Reserve UID 1 so the fixture account does not bypass entity access.
    User::create(['name' => 'content-index-query-root-fixture', 'status' => 1])->save();
    $reader = User::create(['name' => 'content-index-query-reader', 'status' => 1]);
    $reader->save();
    $other = User::create(['name' => 'content-index-query-other', 'status' => 1]);
    $other->save();
    self::assertNotSame('1', (string) $reader->id());

    $nodes = [];
    for ($index = 1; $index <= 4; $index++) {
      $nodes[$index] = Node::create([
        'type' => 'sample_content',
        'title' => 'Query fixture ' . $index,
        'status' => TRUE,
      ]);
      $nodes[$index]->save();
    }
    $this->buildAndActivateGeneration();

    $query = $this->container->get('aculta_index.query');
    $filters = ['entity_type' => 'node', 'bundle' => 'sample_content'];
    $firstPage = $query->query($filters, $reader, NULL, 2);
    self::assertSame([(string) $nodes[4]->id(), (string) $nodes[3]->id()], $this->entityIds($firstPage->entities));
    self::assertNotNull($firstPage->nextCursor);
    self::assertSame(43, strlen($firstPage->nextCursor));
    self::assertSame(0, $firstPage->cacheability->getCacheMaxAge());

    try {
      $query->query($filters, $other, $firstPage->nextCursor, 2);
      self::fail('A cursor must not be transferable to another account.');
    }
    catch (\InvalidArgumentException) {
      // The cursor remains available to its owner after the rejected attempt.
    }

    // A newly indexed item sorts before the cursor and cannot shift the next
    // page. Candidate-cache invalidation makes it visible to fresh queries.
    $newNode = Node::create([
      'type' => 'sample_content',
      'title' => 'Query fixture added after cursor creation',
      'status' => TRUE,
    ]);
    $newNode->save();
    $this->processWorkFor((string) $newNode->id());

    $nodes[2]->setUnpublished()->save();
    $secondPage = $query->query($filters, $reader, $firstPage->nextCursor, 2);
    self::assertSame([(string) $nodes[1]->id()], $this->entityIds($secondPage->entities), 'Current Entity Access must hide a source unpublished after its projection was written.');
    self::assertSame($firstPage->nextCursor, $secondPage->nextCursor, 'The opaque cursor advances in place while candidates remain.');

    $lastPage = $query->query($filters, $reader, $secondPage->nextCursor, 2);
    self::assertSame([], $lastPage->entities);
    self::assertNull($lastPage->nextCursor);

    $freshPage = $query->query($filters, $reader, NULL, 2);
    self::assertSame((string) $newNode->id(), $freshPage->entities[0]->id(), 'A fresh query sees the new entry after its cache revision advances.');
  }

  /**
   * Queries without an active generation are empty and bounds stay enforced.
   */
  public function testQueryRequiresValidFiltersAndBoundedCandidateLimit(): void {
    $account = User::create(['name' => 'content-index-empty-query', 'status' => 1]);
    $account->save();
    $query = $this->container->get('aculta_index.query');

    $empty = $query->query([], $account);
    self::assertSame([], $empty->entities);
    self::assertNull($empty->nextCursor);

    foreach ([0, 101] as $limit) {
      try {
        $query->query([], $account, NULL, $limit);
        self::fail(sprintf('Candidate limit %d must be rejected.', $limit));
      }
      catch (\InvalidArgumentException) {
        // Expected for out-of-range limits.
      }
    }

    try {
      $query->query(['owner' => '1'], $account);
      self::fail('An unsupported filter must be rejected.');
    }
    catch (\InvalidArgumentException) {
      // Expected for an unsupported filter.
    }
  }

  /**
   * Builds, drains and activates a complete generation for the fixture.
   */
  private function buildAndActivateGeneration(): void {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The sample provider backfill should finish within ten batches.');
    $this->processAllQueuedWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
  }

  /**
   * Processes all queued Content Index work through the production worker.
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
   * Processes work for one source identity while leaving other jobs queued.
   */
  private function processWorkFor(string $entityId): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', $entityId)
      ->condition('status', 'queued')
      ->orderBy('work_id')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Gets returned source IDs in page order.
   *
   * @param list<\Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Authorized query results.
   *
   * @return list<string>
   *   Entity IDs.
   */
  private function entityIds(array $entities): array {
    return array_map(static fn ($entity): string => (string) $entity->id(), $entities);
  }

}
