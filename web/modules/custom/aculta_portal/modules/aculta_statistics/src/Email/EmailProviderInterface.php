<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Email;

/**
 * Funil de e-mails de um dia UTC, lido da fonte (somente leitura).
 */
interface EmailProviderInterface {

  /**
   * Contagens do dia UTC informado, no formato de EmailFunnel::count().
   *
   * @param int $day
   *   Dia UTC (número de dias desde 1970-01-01).
   *
   * @return array<string, array<string, int>>
   *   Totais por métrica e dimensão.
   */
  public function countsForDay(int $day): array;

}
