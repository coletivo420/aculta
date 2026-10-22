<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
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
    ]);
    $this->installConfig(['node']);
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

}
