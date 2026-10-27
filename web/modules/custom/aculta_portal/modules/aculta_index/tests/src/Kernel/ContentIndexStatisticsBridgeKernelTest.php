<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_index\Statistics\ContentIndexStatisticsBridge;
use Drupal\aculta_portal\Statistics\ContentEntityMetricAggregatorInterface;
use Drupal\aculta_statistics\Bridge\AuthorizedEntityMetricAggregator;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Exercises authorized Index selection and real Statistics aggregation.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexStatisticsBridgeKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'file',
    'aculta_index',
    'aculta_index_file',
    'aculta_index_statistics_test',
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

    // Compose only the production Statistics services required by the bridge.
    // This reads the owner's real YAML without enabling its Commerce chain.
    $statisticsServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_statistics/aculta_statistics.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    foreach ([
      'aculta_statistics.catalog',
      'aculta_statistics.storage',
      'aculta_statistics.entity_metric_aggregator',
    ] as $serviceId) {
      $service = $statisticsServices[$serviceId];
      $arguments = array_map(
        static fn (mixed $argument): mixed => is_string($argument) && str_starts_with($argument, '@')
          ? new Reference(substr($argument, 1))
          : $argument,
        $service['arguments'] ?? [],
      );
      $container->setDefinition(
        $serviceId,
        (new Definition($service['class'], $arguments))->setPublic(TRUE),
      );
    }
    $container->setAlias(ContentEntityMetricAggregatorInterface::class, 'aculta_statistics.entity_metric_aggregator')
      ->setPublic(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_statistics/aculta_statistics.install';
    $this->container->get('database')->schema()->createTable(
      DailyAggregateStorage::TABLE,
      aculta_statistics_schema()[DailyAggregateStorage::TABLE],
    );
    $this->installConfig(['node', 'user']);
    aculta_index_install();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry'])->save();
  }

  /**
   * The bridge aggregates only entities passing the real Index access filter.
   */
  public function testRealIndexPageFeedsStatisticsAndExcludesDeniedEntities(): void {
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Public Wiki entry',
      'status' => 1,
    ]);
    $node->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    $this->assertTrue($complete, 'The fixture rebuild should complete within ten batches.');
    $rebuilder->validate($generationId);
    $this->completeQueuedWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $statisticsStorage = $this->container->get('aculta_statistics.storage');
    $this->assertInstanceOf(DailyAggregateStorage::class, $statisticsStorage);
    $statisticsStorage->record('wiki.entries.read', (string) $node->id(), 7);
    $statistics = $this->container->get(ContentEntityMetricAggregatorInterface::class);
    $this->assertInstanceOf(AuthorizedEntityMetricAggregator::class, $statistics);
    $bridge = $this->container->get('aculta_index.statistics_bridge');
    $this->assertInstanceOf(ContentIndexStatisticsBridge::class, $bridge);
    Role::create(['id' => 'metric_reader', 'label' => 'Metric reader'])
      ->grantPermission('access content')
      ->grantPermission('view content-level statistics')
      ->save();
    // UID 1 bypasses Drupal's ordinary entity access checks. Reserve it so
    // this test proves the bridge filters results for a regular account.
    User::create(['name' => 'statistics-bridge-root-fixture', 'status' => 1])->save();
    $reader = User::create(['name' => 'metric-reader', 'status' => 1]);
    $reader->addRole('metric_reader');
    $reader->save();
    $this->assertNotSame('1', (string) $reader->id(), 'The tested reader must not use Drupal UID 1.');
    $this->assertTrue($reader->hasPermission('view content-level statistics'), 'The regular reader has the granular Statistics permission.');

    $result = $bridge->aggregateForQuery(
      ['entity_type' => 'node', 'bundle' => 'wiki_entry', 'published' => TRUE],
      'wiki.entries.read',
      $statisticsStorage->currentDay(),
      $statisticsStorage->currentDay(),
      $reader,
    );

    $this->assertTrue($result->available);
    $this->assertCount(1, $result->entities);
    $this->assertSame((string) $node->id(), (string) $result->entities[0]->id());
    $this->assertSame([(string) $node->id() => 7], $result->totals);

    $fallback = (new ContentIndexStatisticsBridge($this->container->get('aculta_index.query'), NULL))
      ->aggregateForQuery(
        ['entity_type' => 'node', 'bundle' => 'wiki_entry', 'published' => TRUE],
        'wiki.entries.read',
        $statisticsStorage->currentDay(),
        $statisticsStorage->currentDay(),
        $reader,
      );
    $this->assertFalse($fallback->available);
    $this->assertCount(1, $fallback->entities);
    $this->assertSame([], $fallback->totals);

    // A stale public projection must not outlive a source access revocation.
    $node->setUnpublished()->save();
    $deniedResult = $bridge->aggregateForQuery(
      ['entity_type' => 'node', 'bundle' => 'wiki_entry', 'published' => TRUE],
      'wiki.entries.read',
      $statisticsStorage->currentDay(),
      $statisticsStorage->currentDay(),
      $reader,
    );
    $this->assertSame([], $deniedResult->entities);
    $this->assertSame([], $deniedResult->totals);
  }

  /**
   * The bridge preserves Index order/cursor and reports zero for no metrics.
   */
  public function testBridgePreservesOrderCursorAndZeroTotals(): void {
    $nodes = [];
    for ($index = 1; $index <= 3; $index++) {
      $nodes[$index] = Node::create([
        'type' => 'wiki_entry',
        'title' => 'Bridge cursor entry ' . $index,
        'status' => 1,
      ]);
      $nodes[$index]->save();
    }

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 100 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    $this->assertTrue($complete, 'The fixture rebuild should complete from the booted provider inventory.');
    $rebuilder->validate($generationId);
    $this->completeQueuedWork();
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $statisticsStorage = $this->container->get('aculta_statistics.storage');
    $statisticsStorage->record('wiki.entries.read', (string) $nodes[1]->id(), 5);
    Role::create(['id' => 'bridge_cursor_reader', 'label' => 'Bridge cursor reader'])
      ->grantPermission('access content')
      ->grantPermission('view content-level statistics')
      ->save();
    User::create(['name' => 'statistics-bridge-cursor-root', 'status' => 1])->save();
    $reader = User::create(['name' => 'statistics-bridge-cursor-reader', 'status' => 1]);
    $reader->addRole('bridge_cursor_reader');
    $reader->save();

    $bridge = $this->container->get('aculta_index.statistics_bridge');
    $filters = ['entity_type' => 'node', 'bundle' => 'wiki_entry', 'published' => TRUE];
    $day = $statisticsStorage->currentDay();
    $first = $bridge->aggregateForQuery($filters, 'wiki.entries.read', $day, $day, $reader, NULL, 2);
    $this->assertTrue($first->available);
    $this->assertSame([(string) $nodes[3]->id(), (string) $nodes[2]->id()], array_map(
      static fn ($entity): string => (string) $entity->id(),
      $first->entities,
    ));
    $this->assertSame([
      (string) $nodes[3]->id() => 0,
      (string) $nodes[2]->id() => 0,
    ], $first->totals);
    $this->assertNotNull($first->nextCursor);

    $second = $bridge->aggregateForQuery($filters, 'wiki.entries.read', $day, $day, $reader, $first->nextCursor, 2);
    $this->assertSame([(string) $nodes[1]->id()], array_map(
      static fn ($entity): string => (string) $entity->id(),
      $second->entities,
    ));
    $this->assertSame([(string) $nodes[1]->id() => 5], $second->totals);
    $this->assertNull($second->nextCursor);
  }

  /**
   * The file:file metric is reachable now that the Index owns a File provider.
   */
  public function testMediaFileMetricIsReachableThroughTheBridge(): void {
    User::create(['name' => 'statistics-bridge-file-root', 'status' => 1])->save();
    Role::create(['id' => 'file_metric_reader', 'label' => 'File metric reader'])
      ->grantPermission('access content')
      ->grantPermission('administer aculta_statistics')
      ->save();
    $reader = User::create(['name' => 'statistics-bridge-file-reader', 'status' => 1]);
    $reader->addRole('file_metric_reader');
    $reader->save();
    self::assertContains('file_metric_reader', $reader->getRoles(), 'The reader holds the metric role.');
    $this->assertTrue($reader->hasPermission('administer aculta_statistics'), 'The file metric reader has the Statistics admin permission.');

    $file = File::create([
      'uri' => 'public://statistics-bridge-file.txt',
      'filename' => 'statistics-bridge-file.txt',
      'status' => FileInterface::STATUS_PERMANENT,
      'uid' => $reader->id(),
    ]);
    $file->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 50 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    $this->assertTrue($complete, 'The fixture rebuild should complete from the booted provider inventory.');
    $this->completeQueuedWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $statisticsStorage = $this->container->get('aculta_statistics.storage');
    $statisticsStorage->record('media.files.requests', (string) $file->id(), 4);
    $bridge = $this->container->get('aculta_index.statistics_bridge');
    $result = $bridge->aggregateForQuery(
      ['entity_type' => 'file', 'bundle' => 'file', 'published' => TRUE],
      'media.files.requests',
      $statisticsStorage->currentDay(),
      $statisticsStorage->currentDay(),
      $reader,
    );

    $this->assertTrue($result->available);
    $this->assertCount(1, $result->entities);
    $this->assertInstanceOf(FileInterface::class, $result->entities[0]);
    $this->assertSame((string) $file->id(), (string) $result->entities[0]->id());
    $this->assertSame([(string) $file->id() => 4], $result->totals);
  }

  /**
   * Completes queued backfill events so the test generation can be activated.
   */
  private function completeQueuedWork(): void {
    $database = $this->container->get('database');
    $work = $this->container->get('aculta_index.work_manager');
    $workIds = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
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
