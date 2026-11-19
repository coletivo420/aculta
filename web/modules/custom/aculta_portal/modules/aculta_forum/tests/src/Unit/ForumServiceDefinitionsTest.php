<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards explicit service arguments against constructor drift.
 *
 * Route controllers are resolved from the container by class name, so a
 * services.yml definition with fewer arguments than the constructor's
 * required parameters throws ArgumentCountError at request time. Directly
 * constructed unit tests do not see that.
 */
#[Group('aculta_forum')]
final class ForumServiceDefinitionsTest extends UnitTestCase {

  /**
   * Every explicit argument list satisfies its service constructor.
   */
  public function testServiceArgumentsMatchConstructors(): void {
    $module = dirname(__DIR__, 3);
    $decoded = Yaml::decode((string) file_get_contents($module . '/aculta_forum.services.yml'));
    $definitions = is_array($decoded) ? $decoded : [];
    self::assertIsArray($definitions['services'] ?? NULL);
    /** @var array<string, mixed> $services */
    $services = $definitions['services'];

    $checked = 0;
    foreach ($services as $id => $definition) {
      if (!is_array($definition) || !isset($definition['arguments'])) {
        continue;
      }
      $rawClass = $definition['class'] ?? (str_contains($id, '\\') ? $id : NULL);
      $class = is_string($rawClass) ? $rawClass : NULL;
      // Parent definitions (for example a logger channel) inherit their class.
      if ($class === NULL || isset($definition['parent']) || isset($definition['factory'])) {
        continue;
      }
      self::assertIsArray($definition['arguments'], "$id: arguments deve ser uma lista.");
      self::assertTrue(class_exists($class), "$id: classe $class não carrega.");

      $constructor = (new \ReflectionClass($class))->getConstructor();
      $required = $constructor?->getNumberOfRequiredParameters() ?? 0;
      $total = $constructor?->getNumberOfParameters() ?? 0;
      $count = count($definition['arguments']);
      self::assertGreaterThanOrEqual(
        $required,
        $count,
        "$id: $class exige $required argumentos e a definição fornece $count.",
      );
      self::assertLessThanOrEqual(
        $total,
        $count,
        "$id: $class aceita no máximo $total argumentos e a definição fornece $count.",
      );
      $checked++;
    }

    self::assertGreaterThan(20, $checked, 'Poucas definições foram verificadas.');
  }

}
