<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
    ]);
    $this->installConfig(['node', 'user']);
    $this->config('user.role.anonymous')->set('permissions', ['access content'])->save();
    aculta_index_install();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
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
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
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

    $secondPage = $query->query(['published' => TRUE], $account, $firstPage->nextCursor, 2);
    $this->assertCount(0, $secondPage->entities);
    $this->assertNull($secondPage->nextCursor);
    $this->assertSame(0, (int) $this->container->get('database')->select('aculta_index_cursor')->countQuery()->execute()->fetchField());
  }

}
