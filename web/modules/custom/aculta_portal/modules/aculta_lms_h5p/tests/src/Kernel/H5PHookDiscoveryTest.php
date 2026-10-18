<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Hook\H5PLibraryHooks::class, $this->container->get(\Drupal\aculta_lms_h5p\Hook\H5PLibraryHooks::class));
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Hook\H5PLifecycleHooks::class, $this->container->get(\Drupal\aculta_lms_h5p\Hook\H5PLifecycleHooks::class));
    self::assertSame('h5p_editor', $this->container->get('plugin.manager.field.widget')->getDefinition('h5p_editor')['id']);
    self::assertSame('h5p_upload', $this->container->get('plugin.manager.field.widget')->getDefinition('h5p_upload')['id']);
    self::assertSame('h5p_default', $this->container->get('plugin.manager.field.formatter')->getDefinition('h5p_default')['id']);
    self::assertSame('h5paccessuserpoints', $this->container->get('plugin.manager.views.access')->getDefinition('h5paccessuserpoints')['id']);
    $condition = $this->container->get('plugin.manager.condition')->getDefinition('aculta_lms_h5p_group_has_activities');
    self::assertSame('entity:group', $condition['context_definitions']['group']->getDataType());
    self::assertSame('O curso tem atividades interativas (H5P)', (string) $condition['label']);
  }

}
