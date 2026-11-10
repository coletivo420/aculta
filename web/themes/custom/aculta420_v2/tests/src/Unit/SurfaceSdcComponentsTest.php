<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Valida os contratos, schemas e definições dos SDCs de superfície.
 */
final class SurfaceSdcComponentsTest extends UnitTestCase {

  private string $themeDir;

  protected function setUp(): void {
    parent::setUp();
    $this->themeDir = dirname(__DIR__, 3);
  }

  /**
   * Valida a declaração e schema do componente surface.
   */
  public function testSurfaceComponentContract(): void {
    $componentPath = $this->themeDir . '/components/surface/surface.component.yml';
    self::assertFileExists($componentPath);

    $meta = Yaml::parse((string) file_get_contents($componentPath));
    self::assertSame('Superfície', $meta['name']);
    self::assertSame('stable', $meta['status']);
    self::assertSame('Superfícies', $meta['group']);

    // Props
    $props = $meta['props']['properties'] ?? [];
    self::assertArrayHasKey('tone', $props);
    self::assertArrayHasKey('radius', $props);
    self::assertArrayHasKey('spacing', $props);
    self::assertArrayHasKey('elevation', $props);
    self::assertArrayHasKey('border', $props);

    // Slots
    $slots = $meta['slots'] ?? [];
    self::assertArrayHasKey('content', $slots);

    // Assets
    self::assertFileExists($this->themeDir . '/components/surface/surface.twig');
    self::assertFileExists($this->themeDir . '/components/surface/surface.css');
  }

  /**
   * Valida a declaração e schema do componente surface-accent.
   */
  public function testSurfaceAccentComponentContract(): void {
    $componentPath = $this->themeDir . '/components/surface-accent/surface-accent.component.yml';
    self::assertFileExists($componentPath);

    $meta = Yaml::parse((string) file_get_contents($componentPath));
    self::assertSame('Superfície com destaque', $meta['name']);
    self::assertSame('stable', $meta['status']);
    self::assertSame('Superfícies', $meta['group']);

    // Props
    $props = $meta['props']['properties'] ?? [];
    self::assertArrayHasKey('accent', $props);
    self::assertEqualsCanonicalizing(
      ['green', 'yellow', 'red', 'tricolor', 'neutral'],
      $props['accent']['enum']
    );

    self::assertArrayHasKey('position', $props);
    self::assertEqualsCanonicalizing(
      ['start', 'top', 'full'],
      $props['position']['enum']
    );

    // Slots
    $slots = $meta['slots'] ?? [];
    self::assertArrayHasKey('content', $slots);

    // Assets
    self::assertFileExists($this->themeDir . '/components/surface-accent/surface-accent.twig');
    self::assertFileExists($this->themeDir . '/components/surface-accent/surface-accent.css');
  }

  /**
   * Valida a declaração e schema do componente surface-panel.
   */
  public function testSurfacePanelComponentContract(): void {
    $componentPath = $this->themeDir . '/components/surface-panel/surface-panel.component.yml';
    self::assertFileExists($componentPath);

    $meta = Yaml::parse((string) file_get_contents($componentPath));
    self::assertSame('Painel institucional', $meta['name']);
    self::assertSame('stable', $meta['status']);
    self::assertSame('Superfícies', $meta['group']);

    // Props
    $props = $meta['props']['properties'] ?? [];
    self::assertArrayHasKey('header_variant', $props);
    self::assertEqualsCanonicalizing(
      ['institutional', 'subtle', 'transparent'],
      $props['header_variant']['enum']
    );
    self::assertArrayHasKey('show_header_placeholder', $props);

    // Slots
    $slots = $meta['slots'] ?? [];
    self::assertArrayHasKey('header', $slots);
    self::assertArrayHasKey('content', $slots);
    self::assertArrayHasKey('footer', $slots);
    self::assertArrayHasKey('actions', $slots);

    // Assets
    self::assertFileExists($this->themeDir . '/components/surface-panel/surface-panel.twig');
    self::assertFileExists($this->themeDir . '/components/surface-panel/surface-panel.css');
  }

  /**
   * Valida as definições do UI Styles correspondentes às superfícies.
   */
  public function testUiStylesSurfaceRegistration(): void {
    $stylesPath = $this->themeDir . '/aculta420_v2.ui_styles.yml';
    self::assertFileExists($stylesPath);

    $styles = Yaml::parse((string) file_get_contents($stylesPath));
    self::assertArrayHasKey('surface_style', $styles);
    self::assertArrayHasKey('surface_accent_style', $styles);
    self::assertArrayHasKey('surface_elevation_style', $styles);
    self::assertArrayHasKey('surface_radius_style', $styles);

    // Variantes de destaque
    $accentOptions = $styles['surface_accent_style']['options'];
    self::assertArrayHasKey('aculta-surface-accent--green', $accentOptions);
    self::assertArrayHasKey('aculta-surface-accent--yellow', $accentOptions);
    self::assertArrayHasKey('aculta-surface-accent--red', $accentOptions);
    self::assertArrayHasKey('aculta-surface-accent--tricolor', $accentOptions);
    self::assertArrayHasKey('aculta-surface-accent--neutral', $accentOptions);
  }

}
