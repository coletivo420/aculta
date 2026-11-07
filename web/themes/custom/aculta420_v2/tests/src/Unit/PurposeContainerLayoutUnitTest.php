<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Layout\PurposeContainerLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida o modelo, sanitização e fallback de layout de container por purpose.
 */
#[CoversClass(PurposeContainerLayout::class)]
#[Group('aculta420_v2')]
final class PurposeContainerLayoutUnitTest extends UnitTestCase {

  /**
   * O padrão de todos os purposes é o modo Padrão, com 1200 px e 750 px.
   */
  public function testDefaultValues(): void {
    foreach (PurposeContainerLayout::ALLOWED_PURPOSES as $purpose) {
      if (PurposeContainerLayout::isCodeControlled($purpose)) {
        continue;
      }
      $defaults = PurposeContainerLayout::getDefaultSettings($purpose);
      self::assertSame('default', $defaults['mode']);
      self::assertSame(1200, $defaults['container_width']);
      self::assertSame(750, $defaults['narrow_width']);
    }
  }

  /**
   * O fórum tem layout fixo pelo código: modo padrão (1200 px), sem caixa, ignorando valor salvo.
   */
  public function testForumLayoutIsFixedByCode(): void {
    self::assertTrue(PurposeContainerLayout::isCodeControlled('forum'));
    $saved = ['mode' => 'default', 'style' => 'boxed', 'container_width' => 900, 'narrow_width' => 600];
    $setting = PurposeContainerLayout::sanitizeSetting($saved, 'forum');
    self::assertSame('default', $setting['mode']);
    self::assertSame('unboxed', $setting['style']);
  }

  /**
   * UI Patterns documentation does not use purpose container settings.
   */
  public function testComponentLibraryRoutesAreExcludedFromPurposeLayout(): void {
    foreach ([
      'ui_patterns_library.overview',
      'ui_patterns_library.provider',
      'ui_patterns_library.single',
    ] as $routeName) {
      self::assertTrue(PurposeContainerLayout::isComponentLibraryRoute($routeName));
    }

    self::assertFalse(PurposeContainerLayout::isComponentLibraryRoute('aculta_studio.dashboard'));
    self::assertFalse(PurposeContainerLayout::isComponentLibraryRoute(NULL));
  }

  /**
   * Os três modos atuais são os únicos oferecidos.
   */
  public function testModeOptionsAreThreeModes(): void {
    $options = PurposeContainerLayout::getModeOptions();
    self::assertSame(['default', 'full', 'narrow'], array_keys($options));
    self::assertSame(['default', 'full', 'narrow'], PurposeContainerLayout::ALLOWED_MODES);
  }

  /**
   * Revista (magazine) e Wiki (wiki) fazem parte dos purposes configuráveis.
   */
  public function testRevistaAndWikiAreConfigurable(): void {
    self::assertContains('magazine', PurposeContainerLayout::ALLOWED_PURPOSES);
    self::assertContains('wiki', PurposeContainerLayout::ALLOWED_PURPOSES);
    $all = PurposeContainerLayout::sanitizeAll([
      'magazine' => ['mode' => 'full'],
      'wiki' => ['mode' => 'narrow', 'narrow_width' => 680],
    ]);
    self::assertSame('full', $all['magazine']['mode']);
    self::assertSame('narrow', $all['wiki']['mode']);
    self::assertSame(680, $all['wiki']['narrow_width']);
  }

  /**
   * Valores válidos passam; modos e larguras fora da faixa caem no padrão.
   */
  public function testSanitizeAcceptsValidAndRejectsInvalid(): void {
    $valid = PurposeContainerLayout::sanitizeSetting([
      'mode' => 'full',
      'container_width' => 1400,
      'narrow_width' => 800,
    ], 'main');
    self::assertSame(['mode' => 'full', 'style' => 'boxed', 'container_width' => 1400, 'narrow_width' => 800], $valid);

    $invalid = PurposeContainerLayout::sanitizeSetting([
      'mode' => 'x; background:red',
      'container_width' => 5000,
      'narrow_width' => 100,
    ], 'main');
    self::assertSame('default', $invalid['mode']);
    self::assertSame(1200, $invalid['container_width']);
    self::assertSame(750, $invalid['narrow_width']);
  }

  /**
   * Valores gravados pelos modos antigos são convertidos para os novos.
   */
  public function testLegacyModesAreMigrated(): void {
    self::assertSame('default', PurposeContainerLayout::sanitizeSetting(['mode' => 'boxed'])['mode']);
    self::assertSame('full', PurposeContainerLayout::sanitizeSetting(['mode' => 'unboxed'])['mode']);
    self::assertSame('default', PurposeContainerLayout::sanitizeSetting(['mode' => 'contained'])['mode']);
    self::assertSame('default', PurposeContainerLayout::sanitizeSetting(['mode' => 'max_full_width'])['mode']);
  }

  /**
   * A resolução gera o atributo de modo e as duas variáveis CSS.
   */
  public function testResolveEmitsModeAndVariables(): void {
    $resolved = PurposeContainerLayout::resolveContainerStyle([
      'magazine' => ['mode' => 'narrow', 'container_width' => 1200, 'narrow_width' => 700],
    ], 'magazine');

    self::assertNotNull($resolved);
    self::assertSame('narrow', $resolved['mode']);
    self::assertSame('1200px', $resolved['variables']['--aculta-container-width']);
    self::assertSame('700px', $resolved['variables']['--aculta-narrow-container-width']);
  }

  /**
   * Purposes fora da lista não recebem layout.
   */
  public function testUnknownPurposeReturnsNull(): void {
    self::assertNull(PurposeContainerLayout::resolveContainerStyle([], 'desconhecido'));
    self::assertNull(PurposeContainerLayout::resolveContainerStyle([], NULL));
  }


  /**
   * Estilo Caixa é o padrão; valores inválidos caem nele.
   */
  public function testStyleDefaultsToBoxedAndRejectsInvalid(): void {
    self::assertSame('boxed', PurposeContainerLayout::getDefaultSettings('main')['style']);
    self::assertSame('boxed', PurposeContainerLayout::sanitizeSetting(['style' => 'x'])['style']);
    self::assertSame('unboxed', PurposeContainerLayout::sanitizeSetting(['style' => 'unboxed'])['style']);
    self::assertSame(['boxed', 'unboxed'], array_keys(PurposeContainerLayout::getStyleOptions()));
  }

  /**
   * O estilo vale para todos os modos, inclusive Largura total.
   */
  public function testStyleAppliesToEveryMode(): void {
    $default = PurposeContainerLayout::resolveContainerStyle(['main' => ['mode' => 'default', 'style' => 'unboxed']], 'main');
    self::assertSame('unboxed', $default['style']);

    $narrow = PurposeContainerLayout::resolveContainerStyle(['main' => ['mode' => 'narrow', 'style' => 'boxed']], 'main');
    self::assertSame('boxed', $narrow['style']);

    $full = PurposeContainerLayout::resolveContainerStyle(['main' => ['mode' => 'full', 'style' => 'unboxed']], 'main');
    self::assertSame('full', $full['mode']);
    self::assertSame('unboxed', $full['style']);
  }

}
