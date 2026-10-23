<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\profile\Entity\ProfileType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\aculta_index\Statistics\ContentIndexStatisticsBridge;
use Drupal\aculta_portal\Statistics\ContentEntityMetricAggregatorInterface;
use Drupal\aculta_statistics\Bridge\AuthorizedEntityMetricAggregator;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Index adapters registered by their independent owner modules.
 *
 * This Index-owned test harness boots independent owner roots together to
 * verify discovery. The LMS does not own the Portal Index.
 */
#[TestGroup('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexOwnerIntegrationKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   *
   * The complete dependency closure is resolved in setUp() from installed
   * module metadata, so this gate follows the owners' real .info.yml contracts.
   *
   * @var string[]
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    static::$modules = $this->resolveModuleDependencyClosure([
      'aculta_portal',
      'aculta_lms',
      'aculta_lms_resources',
      'aculta_pdf_viewer_group',
      'aculta_forum',
      'aculta_wiki',
      'aculta_statistics',
      'schema_web_page',
      'schema_organization',
      'aculta_index',
    ]);
    parent::bootKernel();
  }

  /**
   * Boots the full container and discovers adapters from their owner services.
   */
  public function testOwnerAdaptersAreDiscoveredByThePortalIndex(): void {
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertInstanceOf(
      ContentIndexStatisticsBridge::class,
      $this->container->get('aculta_index.statistics_bridge'),
    );
    self::assertInstanceOf(
      AuthorizedEntityMetricAggregator::class,
      $this->container->get(ContentEntityMetricAggregatorInterface::class),
    );

    self::assertSame('lms_course', $registry->findProviderIdForBundle('group', 'lms_course'));
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('wiki', $registry->findProviderIdForBundle('node', 'wiki_entry'));
    self::assertSame('wiki_category', $registry->findProviderIdForBundle('taxonomy_term', 'wiki_category'));
    self::assertSame('magazine', $registry->findProviderIdForBundle('node', 'revista'));
    self::assertSame('remote_video', $registry->findProviderIdForBundle('media', 'remote_video'));
    self::assertSame('gallery', $registry->findProviderIdForBundle('media_gallery', 'media_gallery'));
    self::assertSame('image', $registry->findProviderIdForBundle('media', 'image'));
    self::assertSame('course_document', $registry->findProviderIdForBundle('media', 'course_document'));
    self::assertSame('forum_attachment', $registry->findProviderIdForBundle('media', 'forum_attachment'));

    $this->installEntitySchema('media');
    $this->installConfig(['aculta_portal']);
    $remoteVideoType = MediaType::load('remote_video');
    self::assertNotNull($remoteVideoType);
    self::assertSame('oembed:video', $remoteVideoType->getSource()->getPluginId());
    self::assertNotNull(FieldConfig::loadByName('media', 'remote_video', 'field_media_oembed_video'));

    $this->assertPdfGroupRelationPluginCanAttachCourseDocument();
  }

  /**
   * Boots the real Wiki/Portal/Index modules and filters Wiki-owned entities.
   *
   * This complements the narrower relation-query fixture tests: here the real
   * Portal entity_access hook, Domain service and all owner service tags are
   * discovered by Drupal's Kernel container without a bridge module.
   */
  public function testRealWikiOwnerBootstrapRevalidatesEntityAccess(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('aculta_statistics', ['aculta_statistics_daily']);
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
    ProfileType::create([
      'id' => 'authenticated',
      'label' => 'Authenticated test profile',
      'display_label' => 'Authenticated test profile',
      'multiple' => FALSE,
      'registration' => FALSE,
      'roles' => ['authenticated'],
      'allow_revisions' => FALSE,
      'new_revision' => FALSE,
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'settings' => ['max_length' => 80, 'case_sensitive' => FALSE, 'is_ascii' => FALSE],
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'authenticated',
      'field_name' => 'field_nickname',
      'label' => 'Nickname',
      'settings' => [],
      'translatable' => FALSE,
    ])->save();
    aculta_index_install();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry', 'new_revision' => TRUE])->save();
    Vocabulary::create(['vid' => 'wiki_category', 'name' => 'Wiki categories'])->save();
    $this->createMediaType('file', ['id' => 'image', 'label' => 'Image']);
    $this->installWikiIndexFields();
    $this->createWikiIndexDomains();

    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->grantPermission('view media')->save();
    User::create(['name' => 'wiki-index-owner-root', 'status' => TRUE])->save();
    $reader = User::create([
      'name' => 'wiki-index-real-reader',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $reader->save();

    $publicTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Visible Wiki target',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $publicTarget->save();
    $privateTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Private Wiki target',
      'status' => FALSE,
      'uid' => 1,
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $privateTarget->save();
    $publicCategory = Term::create(['vid' => 'wiki_category', 'name' => 'Visible category', 'status' => TRUE]);
    $publicCategory->save();
    $privateCategory = Term::create(['vid' => 'wiki_category', 'name' => 'Private category', 'status' => FALSE]);
    $privateCategory->save();
    $publicImage = Media::create([
      'bundle' => 'image',
      'name' => 'Visible image',
      'status' => TRUE,
      'uid' => $reader->id(),
    ]);
    $publicImage->save();
    $privateImage = Media::create([
      'bundle' => 'image',
      'name' => 'Private image',
      'status' => FALSE,
      'uid' => 1,
    ]);
    $privateImage->save();
    $source = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Real Wiki owner source',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_related' => [
        ['target_id' => $publicTarget->id()],
        ['target_id' => $publicTarget->id()],
        ['target_id' => $privateTarget->id()],
      ],
      'field_wiki_categories' => [
        ['target_id' => $publicCategory->id()],
        ['target_id' => $privateCategory->id()],
      ],
      'field_wiki_image' => ['target_id' => $publicImage->id()],
    ]);
    $source->save();
    $privateImageSource = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Real Wiki private image source',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_image' => ['target_id' => $privateImage->id()],
    ]);
    $privateImageSource->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame(3, $registry->get('wiki')->getProjectionVersion());
    $projection = $registry->get('wiki')->project($source);
    self::assertNotNull($projection);
    $relationKeys = [];
    foreach ($projection->relations as $relation) {
      $relationKeys[] = implode(':', [$relation->type, $relation->targetEntityTypeId, $relation->targetEntityId]);
    }
    sort($relationKeys);
    self::assertSame([
      'related_entry:node:' . $publicTarget->id(),
      'related_entry:node:' . $privateTarget->id(),
      'wiki_category:taxonomy_term:' . $publicCategory->id(),
      'wiki_category:taxonomy_term:' . $privateCategory->id(),
      'wiki_image:media:' . $publicImage->id(),
    ], $relationKeys, 'The production Wiki provider projects each configured relation once.');

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    self::assertFalse($source->access('view', $reader), 'The real Portal hook denies Wiki source access in main.');

    $candidates = [];
    foreach ([$source, $publicTarget, $privateTarget, $privateImageSource] as $node) {
      $candidates[] = [
        'entity_type' => 'node',
        'entity_id' => (string) $node->id(),
        'langcode' => $node->language()->getId(),
      ];
    }
    foreach ([$publicCategory, $privateCategory] as $term) {
      $candidates[] = [
        'entity_type' => 'taxonomy_term',
        'entity_id' => (string) $term->id(),
        'langcode' => $term->language()->getId(),
      ];
    }
    foreach ([$publicImage, $privateImage] as $media) {
      $candidates[] = [
        'entity_type' => 'media',
        'entity_id' => (string) $media->id(),
        'langcode' => $media->language()->getId(),
      ];
    }
    $filtered = $this->container->get('aculta_index.access_filter')->filter($candidates, $reader);
    $allowed = [];
    foreach ($filtered->entities as $entity) {
      $allowed[$entity->getEntityTypeId() . ':' . $entity->id()] = TRUE;
    }
    $expectedAllowed = [
      'media:' . $publicImage->id() => TRUE,
      'node:' . $source->id() => TRUE,
      'node:' . $publicTarget->id() => TRUE,
      'node:' . $privateImageSource->id() => TRUE,
      'taxonomy_term:' . $publicCategory->id() => TRUE,
    ];
    ksort($expectedAllowed);
    ksort($allowed);
    self::assertSame($expectedAllowed, $allowed, 'The real Index filter returns only entities viewable under Wiki access rules.');
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id(), 'The original main Domain is restored.');
    self::assertFalse($source->access('view', $reader), 'Wiki access is not cached into the restored main Domain.');
  }

  /**
   * Installs fields that mirror the current Wiki relationship config.
   */
  private function installWikiIndexFields(): void {
    $fields = [
      'field_wiki_related' => ['node', -1],
      'field_wiki_categories' => ['taxonomy_term', -1],
      'field_wiki_image' => ['media', 1],
    ];
    foreach ($fields as $fieldName => [$targetType, $cardinality]) {
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => $fieldName,
        'type' => 'entity_reference',
        'settings' => ['target_type' => $targetType],
        'cardinality' => $cardinality,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => 'wiki_entry',
        'field_name' => $fieldName,
        'label' => $fieldName,
        'settings' => ['handler' => 'default:' . $targetType, 'handler_settings' => []],
      ])->save();
    }
  }

  /**
   * Creates the canonical MAIN and Wiki Domains for the production service.
   */
  private function createWikiIndexDomains(): void {
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

  /**
   * Exercises the PDF owner's real Group relation plugin with persisted data.
   */
  private function assertPdfGroupRelationPluginCanAttachCourseDocument(): void {
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    aculta_index_install();
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['system', 'user', 'file', 'media', 'group']);

    $this->createMediaType('file', [
      'id' => 'course_document',
      'label' => 'Course document',
    ]);
    GroupType::create([
      'id' => 'lms_course',
      'label' => 'Course',
      'creator_membership' => FALSE,
    ])->save();

    $relationManager = $this->container->get('group_relation_type.manager');
    $relationManager->clearCachedDefinitions();
    $definition = $relationManager->getDefinition('aculta_pdf_viewer_group:course_document');
    self::assertSame('media', $definition->getEntityTypeId());
    self::assertSame('course_document', $definition->getEntityBundle());

    $relationshipTypeStorage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypeStorage);
    $relationshipType = $relationshipTypeStorage->createFromPlugin(
      GroupType::load('lms_course'),
      'aculta_pdf_viewer_group:course_document',
    );
    $relationshipType->save();

    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Course document relation fixture',
      'uid' => 1,
    ]);
    $course->save();
    $document = Media::create([
      'bundle' => 'course_document',
      'name' => 'Owner plugin document fixture',
      'status' => FALSE,
    ]);
    $document->save();
    $relationship = $course->addRelationship($document, 'aculta_pdf_viewer_group:course_document');

    self::assertSame('aculta_pdf_viewer_group:course_document', $relationship->getPluginId());
    self::assertSame((string) $document->id(), (string) $relationship->getEntity()->id());
  }

  /**
   * Resolves the real transitive module dependencies in dependency order.
   *
   * @param string[] $roots
   *   Modules whose complete dependency graph must boot.
   *
   * @return string[]
   *   Module IDs ordered with dependencies before their dependents.
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
        throw new \RuntimeException(sprintf('Required Portal/LMS module %s is unavailable.', $module));
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
