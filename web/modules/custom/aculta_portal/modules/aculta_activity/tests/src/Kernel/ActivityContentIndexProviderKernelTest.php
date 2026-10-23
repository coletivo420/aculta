<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Activity provider discovery and projection in the real container.
 */
#[Group('aculta_activity')]
#[RunTestsInSeparateProcesses]
final class ActivityContentIndexProviderKernelTest extends KernelTestBase {

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
    'aculta_activity',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Provider discovery does not exercise Domain negotiation or access.
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
    $this->installConfig(['user', 'node', 'system']);
    aculta_index_install();
    NodeType::create(['type' => 'activity', 'name' => 'Activity'])->save();
  }

  /**
   * Tests the tagged provider is discovered and projects an Activity node.
   */
  public function testTaggedProviderProjectsActivityNode(): void {
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertTrue($registry->has('activity'));
    $provider = $registry->get('activity');
    self::assertInstanceOf(ContentIndexProviderInterface::class, $provider);

    $node = Node::create([
      'type' => 'activity',
      'title' => 'Encontro de teste',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $node->save();
    $work = $this->container->get('aculta_index.work_manager')
      ->getStatus('activity', 'node', (string) $node->id(), 'en');
    self::assertSame('project', $work['operation']);

    $projection = $provider->project($node);
    self::assertNotNull($projection);
    self::assertSame('node', $projection->entityTypeId);
    self::assertSame('activity', $projection->bundle);
    self::assertSame((string) $node->id(), $projection->entityId);
    self::assertSame('en', $projection->langcode);
    self::assertSame('Encontro de teste', $projection->label);
    self::assertSame([], $projection->relations);
    self::assertSame('main', $provider->getAccessPurpose($node));
  }

  /**
   * Tests backfill, incremental update and deletion through the real worker.
   */
  public function testActivityProjectionFollowsEntityCrud(): void {
    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Atividade inicial',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $activity->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Activity provider backfill should complete.');
    $this->processActivityWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    self::assertSame('Atividade inicial', $this->entryLabel($generationId, (string) $activity->id()));

    $activity->setTitle('Atividade atualizada');
    $activity->save();
    $this->processActivityWork();
    self::assertSame('Atividade atualizada', $this->entryLabel($generationId, (string) $activity->id()));

    $activity->delete();
    $this->processActivityWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'activity')
      ->condition('entity_id', (string) $activity->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Processes queued work belonging to the Activity provider.
   */
  private function processActivityWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'activity')
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
   * Returns the label of one Activity entry in a generation.
   */
  private function entryLabel(string $generationId, string $entityId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'activity')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

}
