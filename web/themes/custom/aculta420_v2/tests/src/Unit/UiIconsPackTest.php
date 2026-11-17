<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Component\Serialization\Yaml;

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
    $data = $this->parseYamlFile($filePath);
    self::assertArrayHasKey('aculta_phosphor_regular', $data);
    self::assertArrayHasKey('aculta_phosphor_duotone', $data);
    self::assertArrayHasKey('aculta_fluent_emoji', $data);
    self::assertArrayHasKey('aculta_brocolis', $data);

    // Valida propriedades de cada pack.
    foreach (['aculta_phosphor_regular', 'aculta_phosphor_duotone', 'aculta_fluent_emoji', 'aculta_brocolis'] as $packId) {
      $pack = $data[$packId] ?? NULL;
      self::assertIsArray($pack);
      self::assertTrue($pack['enabled'] ?? FALSE);
      self::assertNotEmpty($pack['label'] ?? '');
      self::assertSame('svg', $pack['extractor'] ?? '');
      $packConfig = $pack['config'] ?? NULL;
      self::assertIsArray($packConfig);
      self::assertNotEmpty($packConfig['sources'] ?? []);
      self::assertNotEmpty($pack['template'] ?? '');
    }

    // Valida que os arquivos SVG dos 3 packs existem nos caminhos configurados.
    $regularFiles = glob($themeDir . '/assets/icons/phosphor/regular/*.svg');
    self::assertIsArray($regularFiles);
    self::assertNotEmpty($regularFiles, 'SVG regular do Phosphor não encontrados.');
    self::assertGreaterThanOrEqual(1000, count($regularFiles));

    $duotoneFiles = glob($themeDir . '/assets/icons/phosphor/duotone/*-duotone.svg');
    self::assertIsArray($duotoneFiles);
    self::assertNotEmpty($duotoneFiles, 'SVG duotone do Phosphor não encontrados.');
    self::assertGreaterThanOrEqual(1000, count($duotoneFiles));

    $brocolisFiles = glob($themeDir . '/assets/icons/aculta-brocolis/*.svg');
    self::assertIsArray($brocolisFiles);
    self::assertCount(6, $brocolisFiles, 'ACULTA Brócolis deve ter seis variantes.');
    foreach ([
      'aculta-brocolis.svg',
      'aculta-brocolis-thin.svg',
      'aculta-brocolis-light.svg',
      'aculta-brocolis-bold.svg',
      'aculta-brocolis-fill.svg',
      'aculta-brocolis-duotone.svg',
    ] as $filename) {
      $svg = (string) file_get_contents($themeDir . '/assets/icons/aculta-brocolis/' . $filename);
      self::assertStringContainsString('viewBox="0 0 256 256"', $svg);
      self::assertStringContainsString('currentColor', $svg);
    }

    $fluentFiles = glob($themeDir . '/assets/icons/fluent-emoji/color/*.svg');
    self::assertIsArray($fluentFiles);
    self::assertNotEmpty($fluentFiles, 'SVG do Fluent Emoji não encontrados.');
    self::assertGreaterThanOrEqual(48, count($fluentFiles));
  }

  /**
   * Confirms the UI Icons integrations and their owned pack restrictions.
   */
  public function testOfficialUiIconsIntegrationsAreConfigured(): void {
    $root = dirname(__DIR__, 7);
    $extensions = $this->parseYamlFile($root . '/config/sync/core.extension.yml');
    $modules = $extensions['module'] ?? NULL;
    self::assertIsArray($modules);
    foreach (['ui_icons', 'ui_icons_field', 'ui_icons_menu', 'ui_icons_patterns', 'ui_icons_picker'] as $module) {
      self::assertArrayHasKey($module, $modules);
      self::assertSame(0, $modules[$module]);
    }
    self::assertArrayNotHasKey('ui_patterns_ckeditor5', $modules);

    $patterns = $this->parseYamlFile($root . '/config/sync/ui_icons_patterns.settings.yml');
    self::assertSame('icon_picker', $patterns['icon_selector'] ?? NULL);

    $allowed_packs = ['aculta_phosphor_regular', 'aculta_phosphor_duotone', 'aculta_brocolis'];
    foreach (['main', 'account', 'admin'] as $menu) {
      $config = $this->parseYamlFile($root . '/config/sync/system.menu.' . $menu . '.yml');
      $thirdPartySettings = $config['third_party_settings'] ?? NULL;
      self::assertIsArray($thirdPartySettings);
      $menuIconSettings = $thirdPartySettings['ui_icons_menu'] ?? NULL;
      self::assertIsArray($menuIconSettings);
      self::assertSame('icon_picker', $menuIconSettings['icon_selector'] ?? NULL);
      self::assertSame($allowed_packs, $menuIconSettings['allowed_icon_pack'] ?? NULL);
      self::assertFalse($menuIconSettings['icon_required'] ?? NULL);
      self::assertTrue($menuIconSettings['icon_position'] ?? NULL);
    }
  }

  /**
   * Parses one YAML file and guarantees its root is a mapping.
   *
   * @return array<array-key, mixed>
   *   Parsed YAML mapping.
   */
  private function parseYamlFile(string $path): array {
    $contents = file_get_contents($path);
    self::assertIsString($contents);
    $data = Yaml::decode($contents);
    self::assertIsArray($data);
    return $data;
  }

}
