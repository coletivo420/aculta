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

    // Valida propriedades de cada pack.
    foreach (['aculta_phosphor_regular', 'aculta_phosphor_duotone', 'aculta_fluent_emoji'] as $packId) {
      $pack = $data[$packId];
      self::assertTrue($pack['enabled'] ?? FALSE);
      self::assertNotEmpty($pack['label'] ?? '');
      self::assertSame('svg', $pack['extractor'] ?? '');
      self::assertNotEmpty($pack['config']['sources'] ?? []);
      self::assertNotEmpty($pack['template'] ?? '');
    }

    // Valida que os arquivos SVG dos 3 packs existem nos caminhos configurados.
    $regularFiles = glob($themeDir . '/assets/icons/phosphor/regular/*.svg');
    self::assertNotEmpty($regularFiles, 'SVG regular do Phosphor não encontrados.');
    self::assertGreaterThanOrEqual(1000, count($regularFiles));

    $duotoneFiles = glob($themeDir . '/assets/icons/phosphor/duotone/*-duotone.svg');
    self::assertNotEmpty($duotoneFiles, 'SVG duotone do Phosphor não encontrados.');
    self::assertGreaterThanOrEqual(1000, count($duotoneFiles));

    $fluentFiles = glob($themeDir . '/assets/icons/fluent-emoji/color/*.svg');
    self::assertNotEmpty($fluentFiles, 'SVG do Fluent Emoji não encontrados.');
    self::assertGreaterThanOrEqual(48, count($fluentFiles));
  }

  /**
   * Confirms the UI Icons integrations and their owned pack restrictions.
   */
  public function testOfficialUiIconsIntegrationsAreConfigured(): void {
    $root = dirname(__DIR__, 7);
    $extensions = Yaml::parseFile($root . '/config/sync/core.extension.yml');
    foreach (['ui_icons', 'ui_icons_field', 'ui_icons_menu', 'ui_icons_patterns', 'ui_icons_picker'] as $module) {
      self::assertArrayHasKey($module, $extensions['module']);
      self::assertSame(0, $extensions['module'][$module]);
    }
    self::assertArrayNotHasKey('ui_patterns_ckeditor5', $extensions['module']);

    $patterns = Yaml::parseFile($root . '/config/sync/ui_icons_patterns.settings.yml');
    self::assertSame('icon_picker', $patterns['icon_selector']);

    $allowed_packs = ['aculta_phosphor_regular', 'aculta_phosphor_duotone'];
    foreach (['main', 'account'] as $menu) {
      $config = Yaml::parseFile($root . '/config/sync/system.menu.' . $menu . '.yml');
      self::assertSame('icon_picker', $config['third_party_settings']['ui_icons_menu']['icon_selector']);
      self::assertSame($allowed_packs, $config['third_party_settings']['ui_icons_menu']['allowed_icon_pack']);
      self::assertFalse($config['third_party_settings']['ui_icons_menu']['icon_required']);
    }
  }

}
