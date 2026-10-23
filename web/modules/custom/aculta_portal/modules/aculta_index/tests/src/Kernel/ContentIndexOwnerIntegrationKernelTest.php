<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Index adapters registered by their independent owner modules.
 *
 * This combined integration harness boots their declared dependency graph;
 * ownership and provider implementations remain in the source modules.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexOwnerIntegrationKernelTest extends KernelTestBase {

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
      'aculta_index',
    ]);
    parent::bootKernel();
  }

  /**
   * Boots the full container and discovers adapters from their owner services.
   */
  public function testPortalLmsOwnerAdaptersAreDiscovered(): void {
    $registry = $this->container->get('aculta_index.provider_registry');

    self::assertSame('lms_course', $registry->findProviderIdForBundle('group', 'lms_course'));
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('wiki', $registry->findProviderIdForBundle('node', 'wiki_entry'));
    self::assertSame('magazine', $registry->findProviderIdForBundle('node', 'revista'));
    self::assertSame('gallery', $registry->findProviderIdForBundle('media_gallery', 'media_gallery'));
    self::assertSame('image', $registry->findProviderIdForBundle('media', 'image'));
    self::assertSame('course_document', $registry->findProviderIdForBundle('media', 'course_document'));
    self::assertSame('forum_attachment', $registry->findProviderIdForBundle('media', 'forum_attachment'));
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
