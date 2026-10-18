<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Checks that Drupal 11 discovers the H5P object-oriented hooks and services. */
#[RunTestsInSeparateProcesses]
final class H5PHookDiscoveryTest extends KernelTestBase {

  /** {@inheritdoc} */
  protected static $modules = ['system', 'user', 'file', 'aculta_image', 'aculta_lms', 'aculta_lms_h5p'];

  /** Tests hook collection and service construction. */
  public function testH5PHooksAndServicesAreRegistered(): void {
    $moduleHandler = $this->container->get('module_handler');
    self::assertTrue($moduleHandler->hasImplementations('library_info_build', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('cron', 'aculta_lms_h5p'));
    self::assertTrue($moduleHandler->hasImplementations('user_delete', 'aculta_lms_h5p'));
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Hook\H5PLibraryHooks::class, $this->container->get(\Drupal\aculta_lms_h5p\Hook\H5PLibraryHooks::class));
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Hook\H5PLifecycleHooks::class, $this->container->get(\Drupal\aculta_lms_h5p\Hook\H5PLifecycleHooks::class));
  }

}
