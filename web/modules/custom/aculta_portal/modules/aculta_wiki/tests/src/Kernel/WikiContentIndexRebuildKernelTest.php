<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Kernel;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\profile\Entity\ProfileType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Exercises a complete Wiki projection generation with the real owner modules.
 */
#[Group('aculta_wiki')]
#[RunTestsInSeparateProcesses]
final class WikiContentIndexRebuildKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * Boots the actual Wiki, Image, Portal, and Content Index dependency graph.
   */
  protected function bootKernel(): void {
    static::$modules = $this->resolveModuleDependencyClosure([
      'aculta_index',
      'aculta_image',
      'aculta_lms',
      'aculta_studio',
      'aculta_user',
      'aculta_wiki',
    ]);
    parent::bootKernel();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'profile', 'path_alias', 'node', 'domain', 'taxonomy_term', 'file', 'media'] as $entityType) {
      $this->installEntitySchema($entityType);
    }
    foreach (PerfilDoPapel::TIPOS as $type) {
      ProfileType::create(['id' => $type, 'label' => $type])->save();
    }
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    foreach (PerfilDoPapel::TIPOS as $type) {
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => $type,
        'field_name' => 'field_nickname',
        'label' => 'Nickname',
      ])->save();
    }
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['system', 'user', 'node', 'domain', 'taxonomy', 'media', 'file']);
    aculta_index_install();
    $this->installWikiFields();
    $this->createDomains();

    $role = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $role);
    $role->grantPermission('access content')->grantPermission('view media')->save();
  }

  /**
   * Rebuilds a generation with the complete seeded Wiki inventory.
   */
  public function testRebuildsCompleteWikiInventoryWithProviderV4(): void {
    $reader = User::create([
      'name' => 'wiki-index-rebuild-reader',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $reader->save();
    $other = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Relacionada',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $other->save();
    $category = Term::create(['vid' => 'wiki_category', 'name' => 'Redução de danos', 'status' => TRUE]);
    $category->save();
    $image = Media::create([
      'bundle' => 'image',
      'name' => 'Imagem pública',
      'status' => TRUE,
      'uid' => $reader->id(),
    ]);
    $image->save();
    $source = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete de integração',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_related' => [
        ['target_id' => $other->id()],
        ['target_id' => $other->id()],
      ],
      'field_wiki_categories' => [['target_id' => $category->id()]],
      'field_wiki_image' => [['target_id' => $image->id()]],
    ]);
    $source->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertTrue($this->container->get('module_handler')->moduleExists('aculta_wiki'));
    self::assertSame('wiki', $registry->findProviderIdForBundle('node', 'wiki_entry'));
    self::assertSame(4, $registry->get('wiki')->getProjectionVersion());
    self::assertSame('wiki_category', $registry->findProviderIdForBundle('taxonomy_term', 'wiki_category'));
    self::assertSame('image', $registry->findProviderIdForBundle('media', 'image'));
    self::assertSame(2, $this->countEntities('node', 'type', 'wiki_entry'));

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $generationManager = $this->container->get('aculta_index.generation_manager');
    self::assertSame($registry->fingerprint(), $generationManager->getProviderFingerprint($generationId));
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 20)['complete'];
    }
    self::assertTrue($complete, 'The real owner providers should finish the isolated generation.');
    $this->processQueuedWork();
    $rebuilder->validate($generationId);

    $database = $this->container->get('database');
    $projected = $database->select('aculta_index_entry', 'entry')
      ->fields('entry', ['provider_version', 'owner_uid', 'projection_hash', 'label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'wiki')
      ->condition('entity_type', 'node')
      ->condition('entity_id', (string) $source->id())
      ->condition('langcode', $source->language()->getId())
      ->execute()
      ->fetchAssoc();
    self::assertIsArray($projected);
    self::assertSame(4, (int) $projected['provider_version']);
    self::assertNull($projected['owner_uid']);
    self::assertNotSame('', $projected['projection_hash']);
    self::assertSame('Verbete de integração', $projected['label']);

    $relations = $database->select('aculta_index_relation', 'relation')
      ->fields('relation', ['relation_type', 'target_entity_type', 'target_entity_id'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'wiki')
      ->condition('source_entity_type', 'node')
      ->condition('source_entity_id', (string) $source->id())
      ->orderBy('relation_type')
      ->execute()
      ->fetchAll();
    $actualRelations = array_map(static fn (object $relation): array => [
      $relation->relation_type,
      $relation->target_entity_type,
      $relation->target_entity_id,
    ], $relations);
    self::assertSame([
      ['related_entry', 'node', (string) $other->id()],
      ['wiki_category', 'taxonomy_term', (string) $category->id()],
      ['wiki_image', 'media', (string) $image->id()],
    ], $actualRelations, 'The full seeded projection contains deduplicated Wiki, category, and image relations.');

    $generationManager->activate($generationId);
    self::assertSame($generationId, $generationManager->getActiveGeneration());
    self::assertSame(2, $this->countIndexed($generationId, 'wiki', 'node', 'wiki_entry'));
    self::assertSame(1, $this->countIndexed($generationId, 'wiki_category', 'taxonomy_term', 'wiki_category'));
    self::assertSame(1, $this->countIndexed($generationId, 'image', 'media', 'image'));
  }

  /**
   * Creates the Wiki and destination fields needed by the projection contract.
   */
  private function installWikiFields(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry', 'new_revision' => TRUE])->save();
    Vocabulary::create(['vid' => 'wiki_category', 'name' => 'Wiki categories'])->save();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->installReferenceField('field_wiki_related', 'node', -1, ['wiki_entry' => 'wiki_entry']);
    $this->installReferenceField('field_wiki_categories', 'taxonomy_term', -1, ['wiki_category' => 'wiki_category']);
    $this->installReferenceField('field_wiki_image', 'media', 1, ['image' => 'image']);
    if (!FieldConfig::loadByName('node', 'wiki_entry', 'field_domain_source')) {
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => 'wiki_entry',
        'field_name' => 'field_domain_source',
        'label' => 'Domain',
        'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
      ])->save();
    }
  }

  /**
   * Adds a typed entity reference field on Wiki entries.
   *
   * @param string $name
   *   Field name.
   * @param string $targetType
   *   Target entity type.
   * @param int $cardinality
   *   Field cardinality.
   * @param array<string, string> $targetBundles
   *   Allowed target bundle IDs and labels.
   */
  private function installReferenceField(string $name, string $targetType, int $cardinality, array $targetBundles): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $name,
      'type' => 'entity_reference',
      'settings' => ['target_type' => $targetType],
      'cardinality' => $cardinality,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => $name,
      'label' => $name,
      'settings' => [
        'handler' => 'default:' . $targetType,
        'handler_settings' => ['target_bundles' => $targetBundles],
      ],
    ])->save();
  }

  /**
   * Creates the domains used by Portal's access purpose resolver.
   */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'wiki420_aculta_org' => 'wiki'] as $id => $purpose) {
      Domain::create([
        'id' => $id,
        'hostname' => $purpose . '.example.invalid',
        'name' => $purpose,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
  }

  /**
   * Processes queued entity work after the backfill pass.
   */
  private function processQueuedWork(): void {
    $database = $this->container->get('database');
    $workIds = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($workIds as $workId) {
      $worker->processItem(['work_id' => (int) $workId]);
    }
  }

  /**
   * Counts source entities of the requested type and bundle.
   */
  private function countEntities(string $entityType, string $field, string $value): int {
    return (int) $this->container->get('entity_type.manager')->getStorage($entityType)->getQuery()
      ->accessCheck(FALSE)
      ->condition($field, $value)
      ->count()
      ->execute();
  }

  /**
   * Counts indexed entities owned by one provider and bundle.
   */
  private function countIndexed(string $generationId, string $providerId, string $entityType, string $bundle): int {
    return (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', $providerId)
      ->condition('entity_type', $entityType)
      ->condition('bundle', $bundle)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Resolves transitive module dependencies in dependency order.
   *
   * @param string[] $roots
   *   Root modules to enable for the test.
   *
   * @return string[]
   *   Enabled module IDs ordered dependencies first.
   */
  private function resolveModuleDependencyClosure(array $roots): array {
    $discovery = new ExtensionDiscovery(DRUPAL_ROOT);
    $discovery->setProfileDirectories([]);
    $extensions = $discovery->scan('module');
    $ordered = [];
    $visited = [];
    $visiting = [];
    $visit = function (string $module) use (&$visit, &$ordered, &$visited, &$visiting, $extensions): void {
      if (isset($visited[$module])) {
        return;
      }
      if (isset($visiting[$module])) {
        throw new \LogicException(sprintf('Circular module dependency detected at %s.', $module));
      }
      if (!isset($extensions[$module])) {
        throw new \RuntimeException(sprintf('Required module %s is unavailable.', $module));
      }
      $visiting[$module] = TRUE;
      $info = Yaml::parseFile(DRUPAL_ROOT . '/' . $extensions[$module]->getPathname());
      foreach ($info['dependencies'] ?? [] as $dependency) {
        if (!is_string($dependency)) {
          continue;
        }
        $dependency = substr($dependency, strrpos($dependency, ':') === FALSE ? 0 : strrpos($dependency, ':') + 1);
        if (isset($extensions[$dependency])) {
          $visit($dependency);
        }
      }
      unset($visiting[$module]);
      $visited[$module] = TRUE;
      $ordered[] = $module;
    };
    foreach ($roots as $root) {
      $visit($root);
    }
    return $ordered;
  }

}
