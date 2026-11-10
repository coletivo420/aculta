<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Unit;

use Drupal\aculta_editor\FluentEmojiCatalog;
use Drupal\aculta_editor\Plugin\Filter\FluentEmojiFilter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a conversão de glifos Unicode em SVGs coloridos do filtro fluent_emoji.
 */
#[CoversClass(FluentEmojiFilter::class)]
#[Group('aculta_editor')]
class FluentEmojiFilterTest extends UnitTestCase {

  /**
   * Filtro com o catálogo real do módulo e URL base previsível.
   */
  private function filter(): FluentEmojiFilter {
    return new FluentEmojiFilter(
      [],
      'fluent_emoji',
      [
        'id' => 'fluent_emoji',
        'provider' => 'aculta_editor',
        'title' => 'ACULTA: emojis Fluent',
      ],
      new FluentEmojiCatalog(),
      '/base/color',
    );
  }

  /**
   * Converte o texto pelo filtro.
   */
  private function process(string $text): string {
    return (string) $this->filter()->process($text, 'pt-br')->getProcessedText();
  }

  /**
   * Glifo simples vira SVG com nome acessível.
   */
  public function testSimpleGlyph(): void {
    $output = $this->process('Fumo 🌿 hoje');

    $this->assertStringContainsString('herb.svg', $output);
    $this->assertStringContainsString('aria-label="Erva"', $output);
    $this->assertStringContainsString('role="img"', $output);
    $this->assertStringNotContainsString('🌿', $output);
  }

  /**
   * Glifo com modificador de tom de pele pardo (U+1F3FD) usa o SVG Medium.
   */
  public function testSkinToneModifier(): void {
    $output = $this->process('✊🏽');

    $this->assertStringContainsString('raised-fist-medium.svg', $output);
    $this->assertStringContainsString('aria-label="Punho Erguido (Pardo)"', $output);
    $this->assertStringNotContainsString('✊🏽', $output);
  }

  /**
   * Todas as mãos do catálogo usam a variante Medium (pele parda).
   */
  public function testAllSkinToneHandsUseMediumAsset(): void {
    $cases = [
      '✌🏽' => 'victory-hand-medium.svg',
      '🙏🏽' => 'folded-hands-medium.svg',
      '👍🏽' => 'thumbs-up-medium.svg',
      '👏🏽' => 'clapping-hands-medium.svg',
    ];

    foreach ($cases as $glyph => $expected) {
      $this->assertStringContainsString($expected, $this->process($glyph), $glyph);
    }
  }

  /**
   * Sequências ZWJ são reconhecidas inteiras, sem sobrar fragmento.
   */
  public function testZwjSequences(): void {
    $cases = [
      '❤️‍🔥' => 'heart-on-fire.svg',
      '😮‍💨' => 'face-exhaling.svg',
      '😶‍🌫️' => 'face-in-clouds.svg',
    ];

    foreach ($cases as $glyph => $expected) {
      $output = $this->process($glyph);
      $this->assertStringContainsString($expected, $output, $glyph);
      $this->assertStringNotContainsString($glyph, $output, $glyph);
    }
  }

  /**
   * Continua funcionando quando o copiar/colar remove U+FE0F.
   */
  public function testVariationSelectorAlias(): void {
    $output = $this->process('☀');

    $this->assertStringContainsString('sun.svg', $output);
    $this->assertStringContainsString('aria-label="Sol"', $output);
  }

  /**
   * Texto sem emojis do catálogo permanece idêntico.
   */
  public function testPlainTextIsUntouched(): void {
    $text = 'Texto comum, sem emoji.';
    $this->assertSame($text, $this->process($text));
  }

  /**
   * Glifos dentro de atributos HTML não são convertidos.
   */
  public function testAttributesAreUntouched(): void {
    $output = $this->process('<a title="🌿">🌿</a>');

    $this->assertStringContainsString('title="🌿"', $output);
    $this->assertStringContainsString('herb.svg', $output);
  }

  /**
   * Blocos de código preservam o glifo literal.
   */
  public function testCodeBlockIsUntouched(): void {
    $output = $this->process('<code>🌿</code>');

    $this->assertStringContainsString('<code>🌿</code>', $output);
    $this->assertStringNotContainsString('herb.svg', $output);
  }

  /**
   * Tags de script, style, svg e nós já convertidos preservam o glifo literal.
   */
  public function testScriptStyleAndSvgAreUntouched(): void {
    $html = '<script>console.log("🌿");</script><style>.a{content:"🌿"}</style><svg viewBox="0 0 10 10"><text>🌿</text></svg><pre><code>🌿</code></pre><p>Texto 🌿</p>';
    $output = $this->process($html);

    $this->assertStringContainsString('<script>console.log("🌿");</script>', $output);
    $this->assertStringContainsString('<style>.a{content:"🌿"}</style>', $output);
    $this->assertStringContainsString('<svg viewBox="0 0 10 10"><text>🌿</text></svg>', $output);
    $this->assertStringContainsString('<pre><code>🌿</code></pre>', $output);
    $this->assertStringContainsString('herb.svg', $output);
  }

  /**
   * Conversão dupla é prevenida quando o HTML já contém marcação reaction-emoji.
   */
  public function testDoubleConversionIsPrevented(): void {
    $html = '<span class="reaction-emoji reaction-emoji--color reaction-emoji--sm" role="img" aria-label="Erva"><img src="/base/color/herb.svg" alt="" width="20" height="20"></span>';
    $output = $this->process($html);

    $this->assertSame($html, $output);
  }

}
