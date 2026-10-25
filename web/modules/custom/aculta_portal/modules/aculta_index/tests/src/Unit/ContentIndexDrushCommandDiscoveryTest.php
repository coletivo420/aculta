<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Verifies Drush discovers the module's attributed commands.
 *
 * @group aculta_index
 */
final class ContentIndexDrushCommandDiscoveryTest extends UnitTestCase {

  /**
   * Drupal module discovery finds all commands in the production directory.
   */
  public function testModuleCommandsAreDiscoverable(): void {
    $directory = DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_index/src/Drush/';
    $discovery = new CommandFileDiscovery();
    $discovery
      ->setIncludeFilesAtBase(TRUE)
      ->setSearchDepth(1)
      ->ignoreNamespacePart('src')
      ->setSearchLocations(['Commands', 'Hooks'])
      ->setSearchPattern('#.*(Command|Hook)s?.php$#');

    $classes = $discovery->discover([$directory], '\Drupal\aculta_index\Drush');
    $expected = [
      'Drupal\aculta_index\Drush\Commands\ContentIndexAuditCommand',
      'Drupal\aculta_index\Drush\Commands\ContentIndexQueueCommand',
      'Drupal\aculta_index\Drush\Commands\ContentIndexRebuildCommand',
    ];
    self::assertEqualsCanonicalizing($expected, array_map(
      static fn (string $class): string => ltrim($class, '\\'),
      array_values($classes),
    ));

    $names = [];
    foreach ($classes as $class) {
      $reflection = new \ReflectionClass($class);
      self::assertTrue($reflection->isSubclassOf(Command::class));
      $attributes = $reflection->getAttributes(AsCommand::class);
      self::assertCount(1, $attributes, $class . ' must declare one Symfony command name.');
      $names[] = $attributes[0]->newInstance()->name;
    }

    self::assertEqualsCanonicalizing([
      'aculta-index:audit',
      'aculta-index:queue',
      'aculta-index:rebuild',
    ], $names);
  }

}
