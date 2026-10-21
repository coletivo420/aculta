<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Chart;

use Drupal\Component\Utility\Html;

/**
 * Gráfico de linhas por dia, em SVG inline: sem JavaScript e sem biblioteca externa.
 *
 * Cada série tem rótulo e cor, e os valores seguem a ordem dos rótulos (dias). Os rótulos e as legendas
 * são escapados. A cor é apoio: a legenda traz o nome de cada série e a página repete os valores em tabela.
 */
final class MailTrendChart {

  private const WIDTH = 640;
  private const HEIGHT = 292;
  private const LEFT = 40;
  private const RIGHT = 16;
  private const TOP = 16;
  private const BOTTOM = 44;

  /**
   * SVG com um desenho por série, eixo Y com três marcas e rótulos de dia (um a cada N, para caber).
   *
   * @param string[] $labels
   *   Rótulos do eixo X, um por dia, em ordem cronológica.
   * @param array<int, array{label: string, color: string, values: int[]}> $series
   *   Séries a desenhar; `values` alinhado a `$labels`.
   * @param string $title
   *   Título acessível do gráfico.
   */
  public static function svg(array $labels, array $series, string $title): string {
    $max = 1;
    foreach ($series as $line) {
      $max = max($max, ...array_map('intval', $line['values'] ?: [0]));
    }
    $plotWidth = self::WIDTH - self::LEFT - self::RIGHT;
    $plotHeight = self::HEIGHT - self::TOP - self::BOTTOM;
    $count = count($labels);
    $xFor = static fn (int $index): float => self::LEFT + ($count > 1 ? $index * $plotWidth / ($count - 1) : $plotWidth / 2);
    $yFor = static fn (int $value): float => self::TOP + $plotHeight - ($value / $max) * $plotHeight;

    $out = [];
    foreach ([0, (int) ceil($max / 2), $max] as $tick) {
      $y = $yFor($tick);
      $out[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#d0d0d0" />', self::LEFT, $y, self::WIDTH - self::RIGHT, $y);
      $out[] = sprintf('<text x="%d" y="%.1f" text-anchor="end" font-size="11" fill="#444">%d</text>', self::LEFT - 6, $y + 4, $tick);
    }
    $every = max(1, (int) ceil($count / 8));
    foreach ($labels as $index => $label) {
      if ($index % $every !== 0 && $index !== $count - 1) {
        continue;
      }
      $out[] = sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="11" fill="#444">%s</text>', $xFor($index), self::HEIGHT - 28, Html::escape($label));
    }
    foreach ($series as $line) {
      $points = [];
      foreach (array_values($line['values']) as $index => $value) {
        $points[] = sprintf('%.1f,%.1f', $xFor($index), $yFor((int) $value));
      }
      if ($points === []) {
        continue;
      }
      $color = Html::escape($line['color']);
      $out[] = sprintf('<polyline fill="none" stroke="%s" stroke-width="2" points="%s" />', $color, implode(' ', $points));
      foreach (array_values($line['values']) as $index => $value) {
        $out[] = sprintf('<circle cx="%.1f" cy="%.1f" r="3" fill="%s" />', $xFor($index), $yFor((int) $value), $color);
      }
    }
    $legend = [];
    $x = self::LEFT;
    foreach ($series as $line) {
      $legend[] = sprintf('<rect x="%d" y="%d" width="10" height="10" fill="%s" /><text x="%d" y="%d" font-size="12" fill="#222">%s</text>', $x, self::HEIGHT - 18, Html::escape($line['color']), $x + 14, self::HEIGHT - 9, Html::escape($line['label']));
      $x += 150;
    }

    return sprintf(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="100%%" role="img" aria-label="%s"><title>%s</title>%s%s</svg>',
      self::WIDTH,
      self::HEIGHT,
      Html::escape($title),
      Html::escape($title),
      implode('', $out),
      implode('', $legend),
    );
  }

}
