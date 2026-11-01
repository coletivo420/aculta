<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Chart\MailTrendChart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Gráfico de tendência em SVG: uma linha por série, valores escapados, sem JavaScript.
 */
#[CoversClass(MailTrendChart::class)]
#[Group('aculta_mailer')]
final class MailTrendChartTest extends TestCase {

  /**
   * Cada série vira uma polyline com um ponto por dia.
   */
  public function testOnePolylinePerSeries(): void {
    $svg = MailTrendChart::svg(['01/10', '02/10', '03/10'], [
      ['label' => 'Confirmados', 'color' => '#2e7d32', 'values' => [1, 2, 0]],
      ['label' => 'Com falha', 'color' => '#c62828', 'values' => [0, 1, 3]],
    ], 'Envios');

    $this->assertSame(2, substr_count($svg, '<polyline'));
    $this->assertSame(6, substr_count($svg, '<circle'), 'Um marcador por ponto.');
    $this->assertStringContainsString('<svg', $svg);
    $this->assertStringContainsString('role="img"', $svg);
  }

  /**
   * Rótulos e títulos vêm escapados: nada de HTML vindo do nome da série.
   */
  public function testLabelsAreEscaped(): void {
    $svg = MailTrendChart::svg(['<b>x</b>'], [
      ['label' => '<script>alert(1)</script>', 'color' => '#000', 'values' => [1]],
    ], 'T & "t"');

    $this->assertStringNotContainsString('<script>', $svg);
    $this->assertStringNotContainsString('<b>x</b>', $svg);
    $this->assertStringContainsString('&lt;script&gt;', $svg);
  }

  /**
   * Sem série, o gráfico continua válido: eixos e nenhuma linha.
   */
  public function testEmptySeriesStillRenders(): void {
    $svg = MailTrendChart::svg(['01/10'], [], 'Vazio');

    $this->assertStringContainsString('<svg', $svg);
    $this->assertSame(0, substr_count($svg, '<polyline'));
  }

}
