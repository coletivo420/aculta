<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Hook\SurfaceSettingsHooks;
use Drupal\aculta420_v2\Palette\PurposePaletteSchemes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida a resolução do esquema de cor por purpose no HTML e CSS variables.
 */
#[CoversClass(SurfaceSettingsHooks::class)]
#[Group('aculta420_v2')]
final class PurposePaletteResolutionUnitTest extends UnitTestCase {

  /**
   * Garante que a resolução do esquema extrai as variáveis corretas para os dois modos.
   */
  public function testPurposePaletteStyleResolution(): void {
    $paletteConfig = [
      'main' => 'amarelo-lemon-haze',
      'account' => 'verde-afghan',
    ];

    $resolvedMain = SurfaceSettingsHooks::purposePaletteStyle($paletteConfig, 'main');
    self::assertNotNull($resolvedMain);
    self::assertSame('amarelo-lemon-haze', $resolvedMain['scheme']);

    $scheme = PurposePaletteSchemes::SCHEMES['amarelo-lemon-haze'];
    self::assertSame($scheme['light']['accent'], $resolvedMain['variables']['--aculta-purpose-light-accent']);
    self::assertSame($scheme['light']['on_accent'], $resolvedMain['variables']['--aculta-purpose-light-on-accent']);
    self::assertSame($scheme['dark']['accent'], $resolvedMain['variables']['--aculta-purpose-dark-accent']);
    self::assertSame($scheme['dark']['on_accent'], $resolvedMain['variables']['--aculta-purpose-dark-on-accent']);

    // Purpose inválido ou não permitido retorna NULL.
    self::assertNull(SurfaceSettingsHooks::purposePaletteStyle($paletteConfig, 'invalido'));
    self::assertNull(SurfaceSettingsHooks::purposePaletteStyle($paletteConfig, NULL));

    // Esquema inexistente na paleta retorna NULL.
    $brokenConfig = ['main' => 'esquema-inexistente'];
    self::assertNull(SurfaceSettingsHooks::purposePaletteStyle($brokenConfig, 'main'));
  }

  /**
   * Garante que os atributos data-purpose-scheme e style são aplicados em html_attributes.
   */
  public function testHtmlAttributesApplication(): void {
    $paletteConfig = ['main' => 'vermelho-red-kush'];
    $resolved = SurfaceSettingsHooks::purposePaletteStyle($paletteConfig, 'main');
    self::assertNotNull($resolved);

    $htmlAttributes = new Attribute();
    $htmlAttributes->setAttribute('data-purpose', 'main');
    $htmlAttributes->setAttribute('data-purpose-scheme', $resolved['scheme']);

    $styles = [];
    foreach ($resolved['variables'] as $name => $value) {
      $styles[] = $name . ': ' . $value . ';';
    }
    $htmlAttributes->setAttribute('style', implode(' ', $styles));

    $rendered = (string) $htmlAttributes;
    self::assertStringContainsString('data-purpose-scheme="vermelho-red-kush"', $rendered);
    self::assertStringContainsString('--aculta-purpose-light-accent: #c03d27;', $rendered);
    self::assertStringContainsString('--aculta-purpose-dark-accent: #dd4329;', $rendered);
  }

}
