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
    'aculta_index',
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

    $database = $this->container->get('database');
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
