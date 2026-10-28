<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Controller\EmbedController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Ensures account-scoped calendar embeds are never stored in shared caches.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class EmbedControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'key', 'aculta_calendar'];

  /**
   * The rendered iframe response is private and uncacheable.
   */
  public function testResponseIsPrivateAndNoStore(): void {
    $response = EmbedController::create($this->container)->page();

    $cacheControl = (string) $response->headers->get('Cache-Control');
    $this->assertStringContainsString('private', $cacheControl);
    $this->assertStringContainsString('no-store', $cacheControl);
    $this->assertStringContainsString('max-age=0', $cacheControl);
    $this->assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
  }

}
