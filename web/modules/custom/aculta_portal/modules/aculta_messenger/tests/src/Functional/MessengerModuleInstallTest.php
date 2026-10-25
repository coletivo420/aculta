<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the Messenger can install without site-global editorial config.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class MessengerModuleInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_messenger'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $public_files = DRUPAL_ROOT . '/' . $this->publicFilesDirectory;
    self::assertDirectoryExists($public_files);
    self::assertTrue(chmod($public_files, 02770));
  }

  /**
   * The target module and its dependencies install and serve a real route.
   */
  public function testModuleInstallsAndServesLoginRoute(): void {
    self::assertTrue($this->container->get('module_handler')->moduleExists('aculta_messenger'));
    $this->drupalGet('/user/login');
    $this->assertSession()->statusCodeEquals(200);
  }

}
