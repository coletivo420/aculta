<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that optional integrations do not expose workers to cron when off.
 */
#[Group('aculta_messenger')]
final class OptionalQueueWorkerDefinitionsTest extends UnitTestCase {

  /**
   * Removes disabled adapter workers and leaves unrelated definitions intact.
   */
  public function testQueueWorkersFollowOptionalModuleAvailability(): void {
    require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_messenger/aculta_messenger.module';
    $previousContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    $enabledModules = ['aculta_wiki'];
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->willReturnCallback(
      static fn(string $module): bool => in_array($module, $enabledModules, TRUE),
    );
    $container = new ContainerBuilder();
    $container->set('module_handler', $moduleHandler);
    \Drupal::setContainer($container);

    try {
      $definitions = array_fill_keys([
        'aculta_lms_messenger_email',
        'aculta_lms_deadline_reminders',
        'aculta_wiki_messenger_email',
        'aculta_commerce_messenger_email',
        'aculta_messenger_donation_email',
        'unrelated_queue_worker',
      ], []);

      \aculta_messenger_queue_info_alter($definitions);

      self::assertSame([
        'aculta_wiki_messenger_email',
        'unrelated_queue_worker',
      ], array_keys($definitions));
    }
    finally {
      if ($previousContainer !== NULL) {
        \Drupal::setContainer($previousContainer);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
  }

}
