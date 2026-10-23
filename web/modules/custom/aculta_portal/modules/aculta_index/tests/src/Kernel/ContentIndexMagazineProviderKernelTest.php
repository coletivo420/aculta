<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the production Magazine provider through Index backfill and CRUD.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexMagazineProviderKernelTest extends KernelTestBase {

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
    'aculta_index_magazine_test',
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
    $this->installConfig(['node', 'user']);
    aculta_index_install();
    NodeType::create(['type' => 'revista', 'name' => 'Revista'])->save();
    NodeType::create(['type' => 'activity', 'name' => 'Activity'])->save();
    $this->installRelatedActivityField();
  }

  /**
   * Backfills a Revista, updates its projection and relation, then deletes it.
   */
  public function testMagazineProjectionFollowsEntityCrud(): void {
    $activityA = Node::create(['type' => 'activity', 'title' => 'Activity A', 'status' => 1]);
    $activityA->save();
    $activityB = Node::create(['type' => 'activity', 'title' => 'Activity B', 'status' => 1]);
    $activityB->save();

    $publication = Node::create([
      'type' => 'revista',
      'title' => 'First title',
      'status' => 1,
      'field_related_activity' => [['target_id' => $activityA->id()]],
    ]);
    $publication->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    $this->assertTrue($complete, 'The Magazine provider backfill should complete.');
    $this->processMagazineWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $database = $this->container->get('database');
    $this->assertSame('First title', $this->entryLabel($generationId, (string) $publication->id()));
    $this->assertSame([(string) $activityA->id()], $this->relationTargets($generationId, (string) $publication->id()));

    $publication->setTitle('Updated title');
    $publication->set('field_related_activity', [['target_id' => $activityB->id()]]);
    $publication->save();
    $this->processMagazineWork();
    $this->assertSame('Updated title', $this->entryLabel($generationId, (string) $publication->id()));
    $this->assertSame([(string) $activityB->id()], $this->relationTargets($generationId, (string) $publication->id()));

    $publication->delete();
    $this->processMagazineWork();
    $this->assertSame(0, (int) $database->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'magazine')
      ->condition('entity_id', (string) $publication->id())
      ->countQuery()
      ->execute()
      ->fetchField());
    $this->assertSame([], $this->relationTargets($generationId, (string) $publication->id()));
  }

  /**
   * Creates the explicit node reference used by the Magazine projection.
   */
  private function installRelatedActivityField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_related_activity',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_related_activity',
      'entity_type' => 'node',
      'bundle' => 'revista',
      'label' => 'Related activity',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['activity' => 'activity']],
      ],
    ])->save();
  }

  /**
   * Processes all queued work belonging to the Magazine provider.
   */
  private function processMagazineWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'magazine')
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Returns one Magazine projection's label.
   */
  private function entryLabel(string $generationId, string $entityId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'magazine')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns the Magazine provider's related-activity target IDs.
   *
   * @return string[]
   *   Related activity node IDs.
   */
  private function relationTargets(string $generationId, string $entityId): array {
    return array_map('strval', $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->fields('relation', ['target_entity_id'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'magazine')
      ->condition('source_entity_id', $entityId)
      ->condition('relation_type', 'related_activity')
      ->orderBy('target_entity_id')
      ->execute()
      ->fetchCol());
  }

}
