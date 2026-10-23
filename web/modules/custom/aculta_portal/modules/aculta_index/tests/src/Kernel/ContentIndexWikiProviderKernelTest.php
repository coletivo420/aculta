<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the production Wiki provider through Index backfill and CRUD.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexWikiProviderKernelTest extends KernelTestBase {

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

    // Compile the production provider declaration from the owner module
    // without enabling the Wiki's unrelated service dependencies.
    $ownerServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_wiki/aculta_wiki.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $service = $ownerServices['aculta_wiki.content_index_provider'];
    $definition = (new Definition($service['class'], $service['arguments'] ?? []))->setPublic(TRUE);
    foreach ($service['tags'] ?? [] as $tag) {
      $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_wiki.content_index_provider', $definition);
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
    NodeType::create([
      'type' => 'wiki_entry',
      'name' => 'Wiki entry',
      'new_revision' => TRUE,
    ])->save();
    $this->installRelatedEntryField();
  }

  /**
   * Backfills Wiki relations, updates a revision and deletes the source entry.
   */
  public function testWikiProjectionFollowsEntityCrud(): void {
    $targetA = Node::create(['type' => 'wiki_entry', 'title' => 'Target A', 'status' => TRUE]);
    $targetA->save();
    $targetB = Node::create(['type' => 'wiki_entry', 'title' => 'Target B', 'status' => TRUE]);
    $targetB->save();
    $source = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Wiki source',
      'status' => TRUE,
      'field_wiki_related' => [
        ['target_id' => $targetA->id()],
        ['target_id' => $targetA->id()],
        ['target_id' => $targetB->id()],
      ],
    ]);
    $source->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('wiki', $registry->findProviderIdForBundle('node', 'wiki_entry'));
    self::assertSame('wiki', $registry->get('wiki')->getAccessPurpose($source));

    $generationId = $this->buildAndActivateGeneration();
    self::assertSame('Wiki source', $this->entryLabel($generationId, (string) $source->id()));
    self::assertSame(
      [(string) $targetA->id(), (string) $targetB->id()],
      $this->relationTargets($generationId, (string) $source->id()),
    );

    $previousRevisionId = $source->getRevisionId();
    $source->setNewRevision(TRUE);
    $source->setTitle('Wiki source updated');
    $source->set('field_wiki_related', [['target_id' => $targetB->id()]]);
    $source->save();
    $previousRevision = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadRevision($previousRevisionId);
    self::assertNull($registry->get('wiki')->project($previousRevision));
    $this->processWikiWork();
    self::assertSame('Wiki source updated', $this->entryLabel($generationId, (string) $source->id()));
    self::assertSame([(string) $targetB->id()], $this->relationTargets($generationId, (string) $source->id()));

    $source->delete();
    $this->processWikiWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'wiki')
      ->condition('entity_type', 'node')
      ->condition('entity_id', (string) $source->id())
      ->countQuery()
      ->execute()
      ->fetchField());
    self::assertSame([], $this->relationTargets($generationId, (string) $source->id()));
  }

  /**
   * Creates the explicit Wiki relation used by the production provider.
   */
  private function installRelatedEntryField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_wiki_related',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_wiki_related',
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'label' => 'Related Wiki entries',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['wiki_entry' => 'wiki_entry']],
      ],
    ])->save();
  }

  /**
   * Builds, drains and activates one complete Index generation.
   */
  private function buildAndActivateGeneration(): string {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Wiki provider backfill should complete.');
    $this->processWikiWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    return $generationId;
  }

  /**
   * Processes queued work belonging to the Wiki provider.
   */
  private function processWikiWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'wiki')
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
   * Returns a Wiki entry's projected label.
   */
  private function entryLabel(string $generationId, string $entityId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'wiki')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns sorted target IDs for a Wiki entry's outgoing relations.
   *
   * @return list<string>
   *   Related entry IDs.
   */
  private function relationTargets(string $generationId, string $entityId): array {
    return $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->fields('relation', ['target_entity_id'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'wiki')
      ->condition('source_entity_type', 'node')
      ->condition('source_entity_id', $entityId)
      ->condition('relation_type', 'related_entry')
      ->orderBy('target_entity_id')
      ->execute()
      ->fetchCol();
  }

}
