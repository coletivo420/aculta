<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
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
    self::assertSame('gallery', $registry->findProviderIdForBundle('media_gallery', 'media_gallery'));
    self::assertSame('image', $registry->findProviderIdForBundle('media', 'image'));
    self::assertSame('course_document', $registry->findProviderIdForBundle('media', 'course_document'));
    self::assertSame('forum_attachment', $registry->findProviderIdForBundle('media', 'forum_attachment'));

    $this->assertPdfGroupRelationPluginCanAttachCourseDocument();
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
