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
    self::assertArrayHasKey('aculta_reacoes', $data);

    // Valida propriedades de cada pack.
    foreach (['aculta_phosphor_regular', 'aculta_phosphor_duotone', 'aculta_fluent_emoji', 'aculta_brocolis', 'aculta_reacoes'] as $packId) {
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

    // Thumbs-down belongs to both existing Fluent Emoji and reaction packs.
    $colorThumbsDown = $themeDir . '/assets/icons/fluent-emoji/color/thumbs-down.svg';
    $flatThumbsDown = $themeDir . '/assets/icons/fluent-emoji/flat/thumbs-down.svg';
    $reactionThumbsDown = $themeDir . '/assets/icons/reacoes/thumbs-down.svg';
    self::assertFileExists($colorThumbsDown);
    self::assertFileExists($flatThumbsDown);
    self::assertFileExists($reactionThumbsDown);
    self::assertSame(hash_file('sha256', $colorThumbsDown), hash_file('sha256', $reactionThumbsDown));
    $colorSvg = file_get_contents($colorThumbsDown);
    $flatSvg = file_get_contents($flatThumbsDown);
    self::assertIsString($colorSvg);
    self::assertIsString($flatSvg);
    self::assertStringContainsString('viewBox="0 0 32 32"', $colorSvg);
    self::assertStringContainsString('viewBox="0 0 32 32"', $flatSvg);
    self::assertSame(['assets/icons/fluent-emoji/color/*.svg'], $data['aculta_fluent_emoji']['config']['sources']);
    self::assertSame(['assets/icons/reacoes/*.svg'], $data['aculta_reacoes']['config']['sources']);
  }

  /**
   * Confirms the UI Icons integrations and their owned pack restrictions.
   */
  public function testOfficialUiIconsIntegrationsAreConfigured(): void {
    $root = dirname(__DIR__, 7);
    $extensions = $this->parseYamlFile($root . '/config/sync/core.extension.yml');
    $modules = $extensions['module'] ?? NULL;
    self::assertIsArray($modules);
    foreach (['ui_icons', 'ui_icons_field', 'ui_icons_menu', 'ui_icons_patterns', 'ui_icons_picker', 'ui_icons_popover'] as $module) {
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
      self::assertSame($menu === 'admin', $menuIconSettings['icon_position'] ?? NULL);
    }

    $formDisplay = $this->parseYamlFile($root . '/config/sync/core.entity_form_display.node.wiki_entry.default.yml');
    $formDependencies = $formDisplay['dependencies'] ?? NULL;
    self::assertIsArray($formDependencies);
    $formModules = $formDependencies['module'] ?? NULL;
    self::assertIsArray($formModules);
    self::assertContains('ui_icons_field', $formModules);
    self::assertContains('ui_icons_popover', $formModules);
    $formContent = $formDisplay['content'] ?? NULL;
    self::assertIsArray($formContent);
    $referenceWidget = $formContent['field_wiki_references'] ?? NULL;
    self::assertIsArray($referenceWidget);
    self::assertSame('icon_link_widget', $referenceWidget['type'] ?? NULL);
    $widgetSettings = $referenceWidget['settings'] ?? NULL;
    self::assertIsArray($widgetSettings);
    self::assertSame('icon_popover', $widgetSettings['icon_selector'] ?? NULL);
    self::assertSame($allowed_packs, $widgetSettings['allowed_icon_pack'] ?? NULL);
    self::assertSame(20, $widgetSettings['max_result'] ?? NULL);
    self::assertFalse($widgetSettings['icon_required'] ?? NULL);
    self::assertFalse($widgetSettings['icon_position'] ?? NULL);

    $viewDisplay = $this->parseYamlFile($root . '/config/sync/core.entity_view_display.node.wiki_entry.default.yml');
    $viewDependencies = $viewDisplay['dependencies'] ?? NULL;
    self::assertIsArray($viewDependencies);
    $viewModules = $viewDependencies['module'] ?? NULL;
    self::assertIsArray($viewModules);
    self::assertContains('ui_icons_field', $viewModules);
    $thirdPartySettings = $viewDisplay['third_party_settings'] ?? NULL;
    self::assertIsArray($thirdPartySettings);
    $layoutBuilder = $thirdPartySettings['layout_builder'] ?? NULL;
    self::assertIsArray($layoutBuilder);
    $sections = $layoutBuilder['sections'] ?? NULL;
    self::assertIsArray($sections);
    $referenceFormatter = NULL;
    foreach ($sections as $section) {
      if (!is_array($section)) {
        continue;
      }
      $components = $section['components'] ?? NULL;
      if (!is_array($components)) {
        continue;
      }
      foreach ($components as $component) {
        if (!is_array($component)) {
          continue;
        }
        $configuration = $component['configuration'] ?? NULL;
        if (!is_array($configuration) || ($configuration['id'] ?? NULL) !== 'field_block:node:wiki_entry:field_wiki_references') {
          continue;
        }
        $referenceFormatter = $configuration['formatter'] ?? NULL;
        break 2;
      }
    }
    self::assertIsArray($referenceFormatter);
    self::assertSame('icon_link_formatter', $referenceFormatter['type'] ?? NULL);
    $formatterSettings = $referenceFormatter['settings'] ?? NULL;
    self::assertIsArray($formatterSettings);
    self::assertSame('before', $formatterSettings['icon_display'] ?? NULL);
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
