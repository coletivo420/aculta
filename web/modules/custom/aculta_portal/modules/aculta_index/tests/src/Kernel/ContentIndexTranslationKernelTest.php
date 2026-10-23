<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the catalog keeps and deletes one projection per translation.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexTranslationKernelTest extends KernelTestBase {

  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'language',
    'content_translation',
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
    $this->installConfig(['system', 'user', 'node', 'language', 'content_translation']);
    aculta_index_install();
    NodeType::create(['type' => 'sample_content', 'name' => 'Sample content'])->save();
    $this->createLanguageFromLangcode('pt-br');
    $this->enableContentTranslation('node', 'sample_content');
  }

  /**
   * Translation removal schedules only that language's catalog identity.
   */
  public function testTranslationRemovalDeletesOnlyItsIndexEntry(): void {
    $node = Node::create([
      'type' => 'sample_content',
      'title' => 'Default language title',
      'status' => TRUE,
      'langcode' => 'en',
    ]);
    $node->save();
    $translation = $node->addTranslation('pt-br', [
      'title' => 'Título em português',
      'status' => TRUE,
    ]);
    $node->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('test_sample_content', $registry->findProviderIdForBundle('node', 'sample_content'));
    $generationId = $this->buildAndActivateGeneration();
    self::assertSame(1, $this->entryCount($generationId, (string) $node->id(), 'en'));
    self::assertSame(1, $this->entryCount($generationId, (string) $node->id(), 'pt-br'));

    $node->removeTranslation('pt-br');
    $node->save();
    $this->processIndexWork();

    self::assertSame(1, $this->entryCount($generationId, (string) $node->id(), 'en'));
    self::assertSame(0, $this->entryCount($generationId, (string) $node->id(), 'pt-br'));
  }

  /**
   * Builds and activates a complete generation for the translation fixture.
   */
  private function buildAndActivateGeneration(): string {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The translation provider backfill should complete.');
    $this->processIndexWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    return $generationId;
  }

  /**
   * Processes all queued source changes through the production worker.
   */
  private function processIndexWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
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
   * Counts a source identity in one language for an active generation.
   */
  private function entryCount(string $generationId, string $entityId, string $langcode): int {
    return (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'test_sample_content')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $entityId)
      ->condition('langcode', $langcode)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
