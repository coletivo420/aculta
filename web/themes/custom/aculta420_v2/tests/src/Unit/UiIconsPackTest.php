<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Valida o registro e metadados dos icon packs do tema aculta420_v2.
 */
final class UiIconsPackTest extends UnitTestCase {

  /**
   * Testa a estrutura do arquivo aculta420_v2.icons.yml.
   */
  public function testIconPacksYamlDefinition(): void {
    $themeDir = dirname(__DIR__, 3);
    $filePath = $themeDir . '/aculta420_v2.icons.yml';

    self::assertFileExists($filePath);
    $data = Yaml::parse((string) file_get_contents($filePath));

    self::assertIsArray($data);
    self::assertArrayHasKey('aculta_phosphor_regular', $data);
    self::assertArrayHasKey('aculta_phosphor_duotone', $data);
    self::assertArrayHasKey('aculta_fluent_emoji', $data);

    // Valida propriedades de cada pack
    foreach (['aculta_phosphor_regular', 'aculta_phosphor_duotone', 'aculta_fluent_emoji'] as $packId) {
      $pack = $data[$packId];
      self::assertTrue($pack['enabled'] ?? FALSE);
      self::assertNotEmpty($pack['label'] ?? '');
      self::assertSame('svg', $pack['extractor'] ?? '');
      self::assertNotEmpty($pack['config']['sources'] ?? []);
      self::assertNotEmpty($pack['template'] ?? '');
    }
  }

}
