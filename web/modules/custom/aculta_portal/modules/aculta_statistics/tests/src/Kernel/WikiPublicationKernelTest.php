<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Hook\WikiPublicationHooks;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\aculta_statistics\Wiki\WikiProvider;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Agregado diário da primeira publicação e da revisão publicada de um verbete.
 */
#[CoversClass(WikiPublicationHooks::class)]
#[CoversClass(WikiProvider::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class WikiPublicationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_statistics', 'node', 'user', 'system', 'field', 'text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
    $this->installConfig(['aculta_statistics']);
    $this->installConfig(['user', 'node']);
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Verbete'])->save();
  }

  /**
   * Um verbete publicado e uma revisão nova geram um registro de cada métrica.
   */
  public function testFirstPublicationAndPublishedRevisionAreAggregated(): void {
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete',
      'status' => 1,
    ]);
    $node->save();
    $nid = (string) $node->id();

    $this->assertSame(1, $this->total(WikiProvider::PUBLISHED, $nid));
    $this->assertSame(0, $this->total(WikiProvider::UPDATED, $nid));

    $node->setNewRevision(TRUE);
    $node->setTitle('Verbete revisado');
    $node->save();

    $this->assertSame(1, $this->total(WikiProvider::PUBLISHED, $nid));
    $this->assertSame(1, $this->total(WikiProvider::UPDATED, $nid));
  }

  /**
   * Rascunho não gera métrica, e publicá-lo conta como primeira publicação.
   */
  public function testDraftDoesNotCountUntilItIsPublished(): void {
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Rascunho',
      'status' => 0,
    ]);
    $node->save();
    $nid = (string) $node->id();

    $this->assertSame(0, $this->total(WikiProvider::PUBLISHED, $nid));
    $this->assertSame(0, $this->total(WikiProvider::UPDATED, $nid));

    $node->setNewRevision(TRUE);
    $node->setPublished();
    $node->save();

    $this->assertSame(1, $this->total(WikiProvider::PUBLISHED, $nid));
    $this->assertSame(0, $this->total(WikiProvider::UPDATED, $nid));
  }

  /**
   * Total agregado da métrica para a dimensão do verbete.
   */
  private function total(string $metric, string $dimension): int {
    $storage = $this->container->get('aculta_statistics.storage');
    return $storage->total($metric, 0, $storage->currentDay() + 1, $dimension);
  }

}
