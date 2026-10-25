<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Report;

/**
 * Linhas de estatísticas da plataforma para o resumo do administrador.
 *
 * Implementado pelo `aculta_statistics`. Opcional: sem ele, o resumo informa que as
 * estatísticas não estão disponíveis, e o restante do resumo segue normal.
 */
interface StatisticsLinesProviderInterface {

  /**
   * Linhas de texto (uma por indicador) para o intervalo [de, até).
   *
   * @return string[]
   *   Linhas já formatadas para o e-mail.
   */
  public function lines(int $from, int $to): array;

}
