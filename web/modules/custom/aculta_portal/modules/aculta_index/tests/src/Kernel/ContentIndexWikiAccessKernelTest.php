<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Symfony\Component\Yaml\Yaml;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\aculta_portal\Hook\EntityHooks;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\ProfileType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies Index filtering under the Portal's production Wiki access policy.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexWikiAccessKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc} */
  protected static $modules = [];

  /**
   * Boots the real Wiki, Image, Portal, and Index modules and dependencies.
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
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
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
    $this->installSchema('comment', ['comment_entity_statistics']);
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
    $this->installDomainFieldStorage();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry'])->save();
    Vocabulary::create(['vid' => 'wiki_category', 'name' => 'Wiki categories'])->save();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->installDomainField();
    $this->createDomains();
    $this->installRelatedEntryField();
    $this->installCategoryField();
    $this->installImageField();
    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
    Role::create([
      'id' => 'wiki_index_media_reader',
      'label' => 'Wiki Index media reader',
      'permissions' => ['access content', 'view media'],
    ])->save();
  }

  /**
   * Real service tag discovery gives each Wiki bundle a single owner.
   */
  public function testRealModulesDiscoverWikiAndMediaProviders(): void {
    self::assertTrue($this->container->get('module_handler')->moduleExists('aculta_wiki'));
    $providers = $this->container->get('aculta_index.provider_registry');
    self::assertSame('wiki', $providers->findForBundle('node', 'wiki_entry')->getProviderId());
    self::assertSame(4, $providers->get('wiki')->getProjectionVersion());
    self::assertSame(1, $providers->get('wiki_category')->getProjectionVersion());
    self::assertSame('wiki_category', $providers->findForBundle('taxonomy_term', 'wiki_category')->getProviderId());
    self::assertSame('image', $providers->findForBundle('media', 'image')->getProviderId());
    self::assertCount(1, array_filter(
      $providers->all(),
      static fn ($provider): bool => in_array('wiki_entry', $provider->getSupportedEntityTypes()['node'] ?? [], TRUE),
    ));
  }

  /**
   * A pending non-default revision cannot replace the public projection.
   */
  public function testPendingRevisionDoesNotReplacePublishedDefaultProjection(): void {
    $this->createTestUser('wiki-revision-bootstrap');
    $owner = $this->createTestUser('wiki-revision-owner');
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Published default revision',
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $entry->save();
    $publishedRevisionId = (int) $entry->getRevisionId();

    $pendingRevision = Node::load($entry->id());
    self::assertInstanceOf(Node::class, $pendingRevision);
    $pendingRevision->setNewRevision(TRUE);
    $pendingRevision->isDefaultRevision(FALSE);
    $pendingRevision->setUnpublished();
    $pendingRevision->setTitle('Pending unpublished revision');
    $pendingRevision->save();

    $revisionStorage = $this->container->get('entity_type.manager')->getStorage('node');
    $storedPendingRevision = $revisionStorage->loadRevision($pendingRevision->getRevisionId());
    self::assertInstanceOf(Node::class, $storedPendingRevision);
    self::assertFalse($storedPendingRevision->isDefaultRevision());
    self::assertNull($this->container->get('aculta_index.provider_registry')->get('wiki')->project($storedPendingRevision));

    $defaultRevision = Node::load($entry->id());
    self::assertInstanceOf(Node::class, $defaultRevision);
    self::assertSame($publishedRevisionId, (int) $defaultRevision->getRevisionId());
    self::assertTrue($defaultRevision->isPublished());
    self::assertNotNull($this->container->get('aculta_index.provider_registry')->get('wiki')->project($defaultRevision));
  }

  /**
   * Real Wiki insert, update, and delete events keep the projection current.
   */
  public function testWikiIncrementalUpdateDeleteAndProviderFingerprint(): void {
    $this->createTestUser('wiki-incremental-bootstrap');
    $owner = $this->createTestUser('wiki-incremental-owner');
    $firstTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'First related target',
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $firstTarget->save();
    $secondTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Second related target',
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $secondTarget->save();
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Wiki before incremental update',
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_related' => [['target_id' => $firstTarget->id()]],
    ]);
    $entry->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $providerRegistry = $this->container->get('aculta_index.provider_registry');
    self::assertSame(
      $providerRegistry->fingerprint(),
      $this->container->get('aculta_index.generation_manager')->getProviderFingerprint($generationId),
    );
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 20)['complete'];
    }
    self::assertTrue($complete, 'The real Wiki provider backfill should complete.');
    $this->processWikiWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $database = $this->container->get('database');
    $getEntry = static function () use ($database, $generationId, $entry): array|false {
      return $database->select('aculta_index_entry', 'entry')
        ->fields('entry', ['label', 'owner_uid', 'provider_version', 'projection_hash'])
        ->condition('generation_id', $generationId)
        ->condition('provider_id', 'wiki')
        ->condition('entity_type', 'node')
        ->condition('entity_id', (string) $entry->id())
        ->condition('langcode', $entry->language()->getId())
        ->execute()
        ->fetchAssoc();
    };
    $getRelatedTargets = static function () use ($database, $generationId, $entry): array {
      return $database->select('aculta_index_relation', 'relation')
        ->fields('relation', ['target_entity_id'])
        ->condition('generation_id', $generationId)
        ->condition('provider_id', 'wiki')
        ->condition('source_entity_type', 'node')
        ->condition('source_entity_id', (string) $entry->id())
        ->condition('source_langcode', $entry->language()->getId())
        ->condition('relation_type', 'related_entry')
        ->orderBy('target_entity_id')
        ->execute()
        ->fetchCol();
    };
    $initial = $getEntry();
    self::assertIsArray($initial, 'The initial Wiki projection is present.');
    self::assertNull($initial['owner_uid'], 'The source author UID is not persisted in the Wiki projection.');
    self::assertSame(4, (int) $initial['provider_version']);
    self::assertSame([(string) $firstTarget->id()], $getRelatedTargets());

    $entry->setTitle('Wiki after incremental update')
      ->set('field_wiki_related', [['target_id' => $secondTarget->id()]])
      ->save();
    $this->processWikiWork();
    $updated = $getEntry();
    self::assertIsArray($updated, 'The updated Wiki projection remains present.');
    self::assertSame('Wiki after incremental update', $updated['label']);
    self::assertNotSame($initial['projection_hash'], $updated['projection_hash']);
    self::assertSame([(string) $secondTarget->id()], $getRelatedTargets());

    $entry->delete();
    $this->processWikiWork();
    self::assertFalse($getEntry(), 'Deleting the Wiki entry removes its projection from the active generation.');
    self::assertSame([], $getRelatedTargets(), 'Deleting the source also removes its outgoing relations.');
  }

  /**
   * Missing canonical Wiki Domain fails closed instead of using main access.
   */
  public function testAccessFilterFailsClosedWhenWikiDomainIsMissing(): void {
    $this->createTestUser('wiki-no-domain-bootstrap');
    $reader = $this->createTestUser('wiki-no-domain-reader');
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Wiki entry without purpose Domain',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $entry->save();
    Domain::load('wiki420_aculta_org')->delete();

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $result = $this->container->get('aculta_index.access_filter')->filter([[
      'entity_type' => 'node',
      'entity_id' => (string) $entry->id(),
      'langcode' => $entry->language()->getId(),
    ],
    ], $reader);

    self::assertSame([], $result->entities);
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
  }

  /**
   * Resolves transitive module dependencies in dependency order.
   *
   * @param string[] $roots
   *   Root modules whose real dependency graph should boot.
   *
   * @return string[]
   *   Module IDs ordered with dependencies before dependents.
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

  /**
   * Canonical Wiki access works from another active Domain and restores it. */
  public function testFilterUsesWikiEntityAccessInCanonicalDomain(): void {
    // Keep the reader away from Drupal's uid 1 superuser shortcut.
    $this->createTestUser('wiki-index-first-user');
    $reader = $this->createTestUser('wiki-index-reader');
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Canonical Wiki entry',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $entry->save();

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $domainNegotiator = $this->container->get(DomainNegotiatorInterface::class);
    $domainNegotiator->getActiveDomain();
    $domainNegotiator->setActiveDomain($mainDomain);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertTrue(
      $this->container->get(EntityHooks::class)
        ->entityAccess($entry, 'view', $reader)?->isForbidden(),
      'The production Portal access policy forbids Wiki entities outside its purpose.',
    );
    $hookResults = $this->container->get('module_handler')
      ->invokeAll('entity_access', [$entry, 'view', $reader]);
    $hasPolicyDenial = FALSE;
    foreach ($hookResults as $hookResult) {
      if ($hookResult instanceof AccessResultInterface && $hookResult->isForbidden()) {
        $hasPolicyDenial = TRUE;
        break;
      }
    }
    self::assertTrue(
      $hasPolicyDenial,
      'Drupal invokes the production Portal entity access hook.',
    );

    self::assertFalse($entry->access('view', $reader), 'The Portal policy blocks Wiki access in the main purpose.');
    $filter = $this->container->get('aculta_index.access_filter');
    $result = $filter->filter([[
      'entity_type' => 'node',
      'entity_id' => (string) $entry->id(),
      'langcode' => $entry->language()->getId(),
    ],
    ], $reader);

    self::assertCount(1, $result->entities, 'The Index rechecks the same entity under its canonical Wiki Domain.');
    self::assertSame('aculta_org', $domainNegotiator->getActiveDomain()?->id(), 'The original Domain is restored.');
    self::assertFalse($entry->access('view', $reader), 'The canonical allowed result is not reused after restoring the main Domain.');
  }

  /**
   * Checks relation source and targets in the canonical Wiki Domain.
   */
  public function testRelatedEntriesRevalidateSourceAndTargetsInWikiDomain(): void {
    $this->createTestUser('wiki-relation-root');
    $reader = $this->createTestUser('wiki-relation-reader');

    $publicTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Public related entry',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $publicTarget->save();
    $privateTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Private related entry',
      'status' => FALSE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $privateTarget->save();
    $source = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Related entries source',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_related' => [
        ['target_id' => $publicTarget->id()],
        ['target_id' => $privateTarget->id()],
      ],
    ]);
    $source->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 20)['complete'];
    }
    self::assertTrue($complete, 'The Wiki provider backfill should complete.');
    $this->processWikiWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    self::assertFalse($source->access('view', $reader), 'Wiki source is not visible from main purpose.');

    $result = $this->container->get('aculta_index.relation_query')->queryOutgoing(
      'node',
      (string) $source->id(),
      $source->language()->getId(),
      'related_entry',
      $reader,
    );
    self::assertCount(1, $result->entities, 'Only the currently authorized Wiki target is returned.');
    self::assertSame('Public related entry', (string) $result->entities[0]->label());
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id(), 'The initiating Domain is restored.');
    self::assertFalse($source->access('view', $reader), 'The relation query does not leak cached Wiki access into main purpose.');
    self::assertSame(0, $result->cacheability->getCacheMaxAge());
  }

  /**
   * Queries Wiki categories and images through their owner providers.
   */
  public function testCategoryAndImageRelationsRevalidateTargetAccess(): void {
    $this->createTestUser('wiki-relations-bootstrap');
    $root = $this->createTestUser('wiki-relations-root');
    $reader = $this->createTestUser('wiki-relations-reader', ['authenticated', 'wiki_index_media_reader']);

    $publicCategory = Term::create([
      'vid' => 'wiki_category',
      'name' => 'Public category',
      'status' => TRUE,
    ]);
    $publicCategory->save();
    $privateCategory = Term::create([
      'vid' => 'wiki_category',
      'name' => 'Private category',
      'status' => FALSE,
    ]);
    $privateCategory->save();
    $publicImage = Media::create([
      'bundle' => 'image',
      'name' => 'Public image',
      'status' => TRUE,
      'uid' => $reader->id(),
    ]);
    $publicImage->save();
    $privateImage = Media::create([
      'bundle' => 'image',
      'name' => 'Private image',
      'status' => FALSE,
      'uid' => $root->id(),
    ]);
    $privateImage->save();

    $source = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Relations source',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_categories' => [
        ['target_id' => $publicCategory->id()],
        ['target_id' => $privateCategory->id()],
      ],
      'field_wiki_image' => ['target_id' => $publicImage->id()],
    ]);
    $source->save();
    $privateImageSource = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Private image relation source',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_image' => ['target_id' => $privateImage->id()],
    ]);
    $privateImageSource->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 20)['complete'];
    }
    self::assertTrue($complete, 'The Index backfill should complete for Wiki records and targets.');
    $this->processWikiWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $relationQuery = $this->container->get('aculta_index.relation_query');
    $categories = $relationQuery->queryOutgoing(
      'node',
      (string) $source->id(),
      $source->language()->getId(),
      'wiki_category',
      $reader,
    );
    self::assertCount(1, $categories->entities);
    self::assertSame('Public category', (string) $categories->entities[0]->label());
    $images = $relationQuery->queryOutgoing(
      'node',
      (string) $source->id(),
      $source->language()->getId(),
      'wiki_image',
      $reader,
    );
    self::assertCount(1, $images->entities);
    self::assertSame('Public image', (string) $images->entities[0]->label());
    $deniedImage = $relationQuery->queryOutgoing(
      'node',
      (string) $privateImageSource->id(),
      $privateImageSource->language()->getId(),
      'wiki_image',
      $root,
    );
    self::assertCount(0, $deniedImage->entities);
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
  }

  /**
   * Processes incremental Wiki work after the backfill.
   */
  private function processWikiWork(): void {
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
   * Creates a real account while keeping AvatarPool outside this access test.
   */
  private function createTestUser(string $name, array $roles = ['authenticated']): User {
    $avatar = File::create([
      'uri' => 'public://wiki-index-avatar-' . bin2hex(random_bytes(4)) . '.png',
      'status' => 0,
    ]);
    $avatar->save();
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => TRUE,
      'roles' => $roles,
      'user_picture' => ['target_id' => $avatar->id()],
    ]);
    $user->save();
    return $user;
  }

  /**
   * Installs the Domain reference storage before Node type creation.
   */
  private function installDomainFieldStorage(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
  }

  /**
   * Installs the Wiki entry's Domain reference field.
   */
  private function installDomainField(): void {
    if (FieldConfig::loadByName('node', 'wiki_entry', 'field_domain_source')) {
      return;
    }
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
      'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
    ])->save();
  }

  /**
   * Installs the outgoing related-entry field used by the Wiki provider.
   */
  private function installRelatedEntryField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_related',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_related',
      'label' => 'Related Wiki entries',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['wiki_entry' => 'wiki_entry']],
      ],
    ])->save();
  }

  /**
   * Installs the multi-value category reference on Wiki entries.
   */
  private function installCategoryField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_categories',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_categories',
      'label' => 'Wiki categories',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['wiki_category' => 'wiki_category']],
      ],
    ])->save();
  }

  /**
   * Installs the single-value image reference on Wiki entries.
   */
  private function installImageField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_image',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_image',
      'label' => 'Wiki image',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => ['target_bundles' => ['image' => 'image']],
      ],
    ])->save();
  }

  /**
   * Creates main and canonical Wiki Domains. */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'wiki420_aculta_org' => 'wiki'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
  }

}
