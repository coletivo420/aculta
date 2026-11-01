<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Kernel;

use Drupal\aculta_activity\ContentIndex\ActivityContentIndexProvider;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the Activity adapter boots when the Content Index is disabled.
 */
#[Group('aculta_activity')]
#[RunTestsInSeparateProcesses]
final class ActivityWithoutIndexKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'aculta_activity',
  ];

  /**
   * The tagged adapter remains available without its optional consumer.
   */
  public function testProviderBootsWithoutIndexConsumer(): void {
    self::assertFalse($this->container->get('module_handler')->moduleExists('aculta_index'));
    self::assertFalse($this->container->has('aculta_index.provider_registry'));

    $provider = $this->container->get('aculta_activity.content_index_provider');
    self::assertInstanceOf(ActivityContentIndexProvider::class, $provider);
    self::assertSame('activity', $provider->getProviderId());
  }

}
