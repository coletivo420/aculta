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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the production Forum provider through Index backfill and CRUD.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexForumTopicProviderKernelTest extends KernelTestBase {

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
    'aculta_index_forum_test',
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
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
  }

  /**
   * Backfills a Forum topic, updates its projection, then deletes it.
   */
  public function testForumTopicProjectionFollowsEntityCrud(): void {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico inicial',
      'status' => TRUE,
    ]);
    $topic->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('forum', $registry->get('forum')->getAccessPurpose($topic));

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Forum provider backfill should complete.');
    $this->processForumWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    self::assertSame('Tópico inicial', $this->entryLabel($generationId, (string) $topic->id()));

    $topic->setTitle('Tópico atualizado');
    $topic->save();
    $this->processForumWork();
    self::assertSame('Tópico atualizado', $this->entryLabel($generationId, (string) $topic->id()));

    $topic->delete();
    $this->processForumWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_id', (string) $topic->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Processes queued work belonging to the Forum provider.
   */
  private function processForumWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'forum')
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
   * Returns the label of one Forum topic entry in a generation.
   */
  private function entryLabel(string $generationId, string $entityId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

}
