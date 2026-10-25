<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\SearchConsole;

/**
 * Calcula o intervalo de datas de uma consulta ao Search Console.
 *
 * Adaptado de `SearchConsoleClient::buildRequest()` (drupal/analyze_search_console,
 * GPL-2.0-or-later, commit 6f054ac). Retorna datas puras, sem objeto do cliente
 * Google, para ser testável sem a biblioteca HTTP.
 *
 * O Search Console agrega os dias no fuso do Pacífico. O chamador informa o dia
 * de referência; esta classe não converte fuso.
 */
final class SearchAnalyticsWindow {

  /**
   * Maior janela aceita (o Search Console guarda cerca de 16 meses).
   */
  public const MAX_DAYS = 480;

  /**
   * Intervalo de `$days` dias terminando em `$today`, inclusive.
   *
   * @return array{startDate: string, endDate: string}
   *   Datas no formato Y-m-d.
   *
   * @throws \InvalidArgumentException
   *   Quando a janela está fora do intervalo aceito.
   */
  public static function forDays(int $days, \DateTimeImmutable $today): array {
    if ($days < 1 || $days > self::MAX_DAYS) {
      throw new \InvalidArgumentException(sprintf('A janela deve ter entre 1 e %d dias.', self::MAX_DAYS));
    }

    $end = $today->setTime(0, 0);
    $start = $end->modify(sprintf('-%d days', $days - 1));

    return [
      'startDate' => $start->format('Y-m-d'),
      'endDate' => $end->format('Y-m-d'),
    ];
  }

}
