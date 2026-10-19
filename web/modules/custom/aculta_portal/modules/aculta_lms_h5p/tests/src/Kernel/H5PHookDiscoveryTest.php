<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/** Checks that Drupal 11 discovers the H5P object-oriented hooks and services. */
#[RunTestsInSeparateProcesses]
final class H5PHookDiscoveryTest extends KernelTestBase {

  /** {@inheritdoc} */
  protected static $modules = ['system', 'user', 'file', 'views', 'aculta_image', 'aculta_lms', 'aculta_lms_h5p'];

  /** Tests hook collection and service construction. */
  public function testH5PHooksAndServicesAreRegistered(): void {
    $moduleHandler = $this->container->get('module_handler');
    self::assertTrue($moduleHandler->hasImplementations('library_info_build', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('cron', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('user_delete', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('field_views_data', 'aculta_lms_h5p'));
    $libraryHooks = $this->container->get(\Drupal\aculta_lms_h5p\Hook\H5PLibraryHooks::class);
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Hook\H5PLibraryHooks::class, $libraryHooks);
    $framework = new \ReflectionMethod(\Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal::class, 'framework');
    $framework->setAccessible(TRUE);
    self::assertSame($this->container->get('aculta_lms_h5p.framework'), $framework->invoke(NULL));
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Hook\H5PLifecycleHooks::class, $this->container->get(\Drupal\aculta_lms_h5p\Hook\H5PLifecycleHooks::class));
    self::assertSame('h5p_editor', $this->container->get('plugin.manager.field.widget')->getDefinition('h5p_editor')['id']);
    self::assertSame('h5p_upload', $this->container->get('plugin.manager.field.widget')->getDefinition('h5p_upload')['id']);
    self::assertSame('h5p_default', $this->container->get('plugin.manager.field.formatter')->getDefinition('h5p_default')['id']);
    self::assertSame('h5paccessuserpoints', $this->container->get('plugin.manager.views.access')->getDefinition('h5paccessuserpoints')['id']);
    self::assertInstanceOf(
      \Drupal\aculta_lms_h5p\Plugin\views\access\H5PAccessUserPoints::class,
      $this->container->get('plugin.manager.views.access')->createInstance('h5paccessuserpoints'),
    );
    $condition = $this->container->get('plugin.manager.condition')->getDefinition('aculta_lms_h5p_group_has_activities');
    self::assertSame('entity:group', $condition['context_definitions']['group']->getDataType());
    self::assertSame('O curso tem atividades interativas (H5P)', (string) $condition['label']);

    // Drupal route placeholders occupy one path segment. H5P sends nested
    // library/content paths and library versions as slash-separated segments,
    // so the route table must include the bounded optional tail segments.
    $nestedPaths = [
      ['/h5p-library-assets/libraries/H5P.HelloWorld-1.0/js/main.js', 'aculta_lms_h5p.library_file'],
      ['/h5p-content/1/2/images/nested/figure.png', 'aculta_lms_h5p.content_file'],
      ['/h5peditor/1/editor-token/0/single-library/H5P.MultiChoice/1/0', 'aculta_lms_h5p.editor_ajax'],
      ['/h5peditor/1/editor-token/0/libraries/H5P.MultiChoice/1/0', 'aculta_lms_h5p.editor_ajax'],
      ['/h5peditor/1/editor-token/0/libraries?machineName=H5P.MultiChoice&majorVersion=1&minorVersion=0', 'aculta_lms_h5p.editor_ajax'],
    ];
    foreach ($nestedPaths as [$path, $expectedRoute]) {
      $routes = $this->container->get('router.route_provider')->getRouteCollectionForRequest(Request::create($path));
      self::assertNotNull($routes->get($expectedRoute), $path);
    }
  }

}
