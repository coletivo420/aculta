<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Garante que toda classe de hook por atributo do módulo esteja registrada como serviço.
 *
 * Sem registro no services.yml, o Core não descobre os métodos #[Hook] e o comportamento some sem erro.
 */
#[Group('aculta_donation')]
final class HookServiceRegistrationTest extends UnitTestCase {

  /**
   * Toda classe em src/Hook que declara #[Hook] aparece no services.yml do módulo.
   */
  public function testEveryAttributeHookClassIsRegisteredAsService(): void {
    $module_dir = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module_dir . '/aculta_donation.services.yml', Yaml::PARSE_CUSTOM_TAGS)['services'];

    $registered = [];
    foreach ($services as $key => $definition) {
      $registered[] = (string) $key;
      if (is_array($definition) && isset($definition['class'])) {
        $registered[] = (string) $definition['class'];
      }
    }

    $missing = [];
    foreach (glob($module_dir . '/src/Hook/*.php') ?: [] as $file) {
      if (!str_contains((string) file_get_contents($file), '#[Hook(')) {
        continue;
      }
      $class = 'Drupal\\aculta_donation\\Hook\\' . basename($file, '.php');
      if (!in_array($class, $registered, TRUE)) {
        $missing[] = $class;
      }
    }

    $this->assertSame([], $missing, 'Classes de hook sem serviço registrado em aculta_donation.services.yml.');
  }

}
