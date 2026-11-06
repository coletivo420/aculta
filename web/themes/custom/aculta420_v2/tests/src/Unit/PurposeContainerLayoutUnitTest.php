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
   * Testa valores padrão para purposes canônicos.
   */
  public function testDefaultValues(): void {
    $defaults = PurposeContainerLayout::getDefaultSettings('main');
    self::assertSame('boxed', $defaults['mode']);
    self::assertSame(1200, $defaults['container_width']);
    self::assertSame(750, $defaults['narrow_width']);

    $options = PurposeContainerLayout::getModeOptions();
    self::assertCount(4, $options);
    self::assertArrayHasKey('boxed', $options);
    self::assertArrayHasKey('unboxed', $options);
    self::assertArrayHasKey('contained', $options);
    self::assertArrayHasKey('max_full_width', $options);
  }

  /**
   * Testa sanitização com valores válidos.
   */
  public function testSanitizationValidValues(): void {
    $validInput = [
      'mode' => 'unboxed',
      'container_width' => 1400,
      'narrow_width' => 800,
    ];

    $sanitized = PurposeContainerLayout::sanitizeSetting($validInput, 'account');
    self::assertSame('unboxed', $sanitized['mode']);
    self::assertSame(1400, $sanitized['container_width']);
    self::assertSame(800, $sanitized['narrow_width']);
  }

  /**
   * Testa fallback para valores inválidos ou fora de faixa (sem injection CSS).
   */
  public function testSanitizationInvalidFallback(): void {
    $maliciousInput = [
      'mode' => '<script>alert(1)</script>; display: none;',
      'container_width' => '999999px; background: red;',
      'narrow_width' => -50,
    ];

    $sanitized = PurposeContainerLayout::sanitizeSetting($maliciousInput, 'support');
    self::assertSame('boxed', $sanitized['mode']);
    self::assertSame(1200, $sanitized['container_width']);
    self::assertSame(750, $sanitized['narrow_width']);

    // Limites mínimo e máximo
    $underflow = [
      'mode' => 'boxed',
      'container_width' => 959,
      'narrow_width' => 599,
    ];
    $sanitizedUnderflow = PurposeContainerLayout::sanitizeSetting($underflow, 'main');
    self::assertSame(1200, $sanitizedUnderflow['container_width']);
    self::assertSame(750, $sanitizedUnderflow['narrow_width']);

    $overflow = [
      'mode' => 'boxed',
      'container_width' => 1601,
      'narrow_width' => 901,
    ];
    $sanitizedOverflow = PurposeContainerLayout::sanitizeSetting($overflow, 'main');
    self::assertSame(1200, $sanitizedOverflow['container_width']);
    self::assertSame(750, $sanitizedOverflow['narrow_width']);
  }

  /**
   * Testa sanitização de toda a árvore de purposes.
   */
  public function testSanitizeAll(): void {
    $all = PurposeContainerLayout::sanitizeAll([
      'main' => ['mode' => 'contained', 'container_width' => 1000, 'narrow_width' => 700],
      'invalid_purpose' => ['mode' => 'unboxed'],
    ]);

    self::assertCount(10, $all);
    self::assertArrayHasKey('main', $all);
    self::assertArrayHasKey('account', $all);
    self::assertArrayNotHasKey('invalid_purpose', $all);
    self::assertSame('contained', $all['main']['mode']);
    self::assertSame(1000, $all['main']['container_width']);
    self::assertSame('boxed', $all['account']['mode']);
  }

  /**
   * Testa resolução de estilo para CSS variables e atributos.
   */
  public function testResolveContainerStyle(): void {
    $config = [
      'account' => [
        'mode' => 'unboxed',
        'container_width' => 1350,
        'narrow_width' => 720,
      ],
      'shop' => [
        'mode' => 'contained',
        'container_width' => 1200, // deve adotar o default do contained (1000)
        'narrow_width' => 750,
      ],
      'eventos' => [
        'mode' => 'max_full_width',
        'container_width' => 1200, // deve adotar o default do max full width (1400)
        'narrow_width' => 750,
      ],
    ];

    $resolvedAccount = PurposeContainerLayout::resolveContainerStyle($config, 'account');
    self::assertNotNull($resolvedAccount);
    self::assertSame('unboxed', $resolvedAccount['mode']);
    self::assertSame('1350px', $resolvedAccount['variables']['--aculta-container-width']);
    self::assertSame('720px', $resolvedAccount['variables']['--aculta-narrow-container-width']);

    $resolvedShop = PurposeContainerLayout::resolveContainerStyle($config, 'shop');
    self::assertNotNull($resolvedShop);
    self::assertSame('contained', $resolvedShop['mode']);
    self::assertSame('1000px', $resolvedShop['variables']['--aculta-container-width']);

    $resolvedEventos = PurposeContainerLayout::resolveContainerStyle($config, 'eventos');
    self::assertNotNull($resolvedEventos);
    self::assertSame('max_full_width', $resolvedEventos['mode']);
    self::assertSame('1400px', $resolvedEventos['variables']['--aculta-container-width']);

    // Purpose desconhecido retorna NULL
    self::assertNull(PurposeContainerLayout::resolveContainerStyle($config, 'desconhecido'));
    self::assertNull(PurposeContainerLayout::resolveContainerStyle($config, NULL));
  }

  /**
   * Testa aplicação dos atributos e variáveis CSS no HTML attributes.
   */
  public function testContainerHtmlAttributesApplication(): void {
    $config = [
      'account' => [
        'mode' => 'unboxed',
        'container_width' => 1450,
        'narrow_width' => 780,
      ],
    ];

    $resolved = PurposeContainerLayout::resolveContainerStyle($config, 'account');
    self::assertNotNull($resolved);

    $htmlAttributes = new \Drupal\Core\Template\Attribute();
    $htmlAttributes->setAttribute('data-purpose', 'account');
    $htmlAttributes->setAttribute('data-purpose-layout', $resolved['mode']);

    $styles = [];
    foreach ($resolved['variables'] as $name => $value) {
      $styles[] = $name . ': ' . $value . ';';
    }
    $htmlAttributes->setAttribute('style', implode(' ', $styles));

    $rendered = (string) $htmlAttributes;
    self::assertStringContainsString('data-purpose="account"', $rendered);
    self::assertStringContainsString('data-purpose-layout="unboxed"', $rendered);
    self::assertStringContainsString('--aculta-container-width: 1450px;', $rendered);
    self::assertStringContainsString('--aculta-narrow-container-width: 780px;', $rendered);
  }

}
