<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Verifies optional installation and DI registration without module-owned data. */
#[Group('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class AcultaLmsInstallTest extends KernelTestBase {

  protected static $modules = ['system'];

  public function testInstallRegistersOnlyTheOptionalIntegrationService(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_lms'], FALSE));
    $container = \Drupal::getContainer();
    $this->assertTrue($container->get('module_handler')->moduleExists('aculta_lms'));
    $this->assertInstanceOf(
      \Drupal\aculta_lms\Event\CourseCompletionPublisherInterface::class,
      $container->get('aculta_lms.course_completion_publisher'),
    );
    $this->assertFalse($container->get('config.storage')->exists('aculta_lms.settings'));
    $this->assertFalse($container->get('module_handler')->hasImplementations('install', ['aculta_lms']));
    $this->assertFalse($container->get('module_handler')->hasImplementations('schema', ['aculta_lms']));
  }

}
