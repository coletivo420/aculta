<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\aculta_lms_h5p\Plugin\views\access\aculta_H5PAccessUserPoints;
use Drupal\aculta_lms_h5p\Hook\aculta_H5PLifecycleHooks;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\Event\aculta_H5PFinishedEvent;
use Drupal\aculta_lms_h5p\Hook\aculta_H5PLibraryHooks;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Stringable;

/**
 * Checks that Drupal 11 discovers the H5P object-oriented hooks and services. */
#[RunTestsInSeparateProcesses]
final class AcultaH5PHookDiscoveryTest extends KernelTestBase {

  use PortalCleanupKernelServicesTrait;

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system', 'user', 'file', 'views', 'aculta_image', 'aculta_lms_h5p',
    'token', 'aculta_file_uploader',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerPortalCleanupServices($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('aculta_lms_h5p', ['h5p_libraries', 'h5p_libraries_libraries']);
  }

  /**
   * Tests hook collection and service construction. */
  public function testHooksAndServicesAreRegistered(): void {
    $moduleHandler = $this->container->get('module_handler');
    self::assertTrue($moduleHandler->hasImplementations('library_info_build', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('cron', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('user_delete', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('field_views_data', 'aculta_lms_h5p'));
    $database = $this->container->get('database');
    $libraryId = $database->insert('h5p_libraries')->fields([
      'machine_name' => 'H5P.TestLibrary',
      'title' => 'Test library',
      'major_version' => 1,
      'minor_version' => 0,
      'patch_version' => 0,
      'semantics' => '{}',
      'preloaded_js' => 'js/test.js',
      'preloaded_css' => 'css/test.css',
    ])->execute();
    $dependencyId = $database->insert('h5p_libraries')->fields([
      'machine_name' => 'H5P.TestDependency',
      'title' => 'Test dependency',
      'major_version' => 2,
      'minor_version' => 3,
      'patch_version' => 0,
      'semantics' => '{}',
    ])->execute();
    $database->insert('h5p_libraries_libraries')->fields([
      'library_id' => $libraryId,
      'required_library_id' => $dependencyId,
      'dependency_type' => 'preloaded',
    ])->execute();

    $libraryHooks = $this->container->get(aculta_H5PLibraryHooks::class);
    $libraries = $libraryHooks->libraryInfoBuild();
    self::assertArrayHasKey('h5p.jquery', $libraries);
    self::assertArrayHasKey('h5p.content', $libraries);
    self::assertArrayHasKey('h5p.editor', $libraries);
    $jquery = $libraries['h5p.jquery'];
    $jqueryScripts = $jquery['js'] ?? NULL;
    self::assertIsArray($jqueryScripts);
    self::assertNotEmpty($jqueryScripts);
    $editor = $libraries['h5p.editor'];
    $editorDependencies = $editor['dependencies'] ?? NULL;
    self::assertIsArray($editorDependencies);
    self::assertContains('aculta_lms_h5p/h5p.content', $editorDependencies);
    $dynamicLibrary = $libraries['h5p.testlibrary-1.0'] ?? NULL;
    self::assertIsArray($dynamicLibrary);
    $scripts = $dynamicLibrary['js'] ?? NULL;
    self::assertIsArray($scripts);
    self::assertSame(['preprocess' => FALSE], $scripts['/' . aculta_H5PDrupal::getRelativeaculta_H5PPath() . '/libraries/H5P.TestLibrary-1.0/js/test.js'] ?? NULL);
    $dependencies = $dynamicLibrary['dependencies'] ?? NULL;
    self::assertIsArray($dependencies);
    self::assertContains('aculta_lms_h5p/h5p.testdependency-2.3', $dependencies);
    $this->container->get('aculta_lms_h5p.asset_file_manager');
    self::assertSame(
      ['score' => 1],
      (new aculta_H5PFinishedEvent(['score' => 1]))->getQuizFields(),
    );
    $framework = new \ReflectionMethod(aculta_H5PDrupal::class, 'framework');
    self::assertSame($this->container->get('aculta_lms_h5p.framework'), $framework->invoke(NULL));
    self::assertInstanceOf(aculta_H5PLifecycleHooks::class, $this->container->get(aculta_H5PLifecycleHooks::class));
    $widgetManager = $this->container->get('plugin.manager.field.widget');
    foreach (['h5p_editor', 'h5p_upload'] as $pluginId) {
      $definition = $widgetManager->getDefinition($pluginId);
      self::assertIsArray($definition);
      self::assertSame($pluginId, $definition['id'] ?? NULL);
    }
    $formatter = $this->container->get('plugin.manager.field.formatter')->getDefinition('h5p_default');
    self::assertIsArray($formatter);
    self::assertSame('h5p_default', $formatter['id'] ?? NULL);
    $access = $this->container->get('plugin.manager.views.access')->getDefinition('h5paccessuserpoints');
    self::assertIsArray($access);
    self::assertSame('h5paccessuserpoints', $access['id'] ?? NULL);
    self::assertSame(
      '/atividades-interativas',
      $this->container->get('router.route_provider')->getRouteByName('aculta_lms_h5p.student_dashboard')->getPath(),
    );
    self::assertInstanceOf(
      aculta_H5PAccessUserPoints::class,
      $this->container->get('plugin.manager.views.access')->createInstance('h5paccessuserpoints'),
    );
    $condition = $this->container->get('plugin.manager.condition')->getDefinition('aculta_lms_h5p_group_has_activities');
    self::assertIsArray($condition);
    $contextDefinitions = $condition['context_definitions'] ?? NULL;
    self::assertIsArray($contextDefinitions);
    $groupContext = $contextDefinitions['group'] ?? NULL;
    self::assertInstanceOf(ContextDefinitionInterface::class, $groupContext);
    self::assertSame('entity:group', $groupContext->getDataType());
    $label = $condition['label'] ?? NULL;
    self::assertInstanceOf(Stringable::class, $label);
    self::assertSame('O curso tem atividades interativas (H5P)', (string) $label);

    // Drupal route placeholders occupy one path segment. H5P sends nested
    // library/content paths and library versions as slash-separated segments,
    // so the route table must include the bounded optional tail segments.
    $nestedPaths = [
      ['/h5p-library-assets/libraries/H5P.HelloWorld-1.0/js/main.js', 'aculta_lms_h5p.library_file'],
      ['/h5p-content/1/2/images/nested/figure.png', 'aculta_lms_h5p.content_file'],
      ['/h5peditor/1/editor-token/0/single-library/H5P.MultiChoice/1/0', 'aculta_lms_h5p.editor_ajax'],
      ['/h5peditor/1/editor-token/0/libraries/H5P.MultiChoice/1/0', 'aculta_lms_h5p.editor_ajax'],
      [
        '/h5peditor/1/editor-token/0/libraries?machineName=H5P.MultiChoice&majorVersion=1&minorVersion=0',
        'aculta_lms_h5p.editor_ajax',
      ],
    ];
    foreach ($nestedPaths as [$path, $expectedRoute]) {
      $routes = $this->container->get('router.route_provider')->getRouteCollectionForRequest(Request::create($path));
      self::assertNotNull($routes->get($expectedRoute), $path);
    }

    self::assertNull(
      $this->container->get('router.route_provider')
        ->getRouteByName('aculta_lms_h5p.editor_ajax')
        ->getOption('_aculta_site_key'),
      'The H5P editor submits same-origin requests from the Group edit form, which belongs to the main purpose.',
    );
  }

}
