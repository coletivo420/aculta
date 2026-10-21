<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Storage;

/**
 * Contadores diários agregados por métrica e dimensão.
 *
 * Contrato do armazenamento central. Não recebe IP, usuário, referrer nem
 * user-agent: quem chama informa somente o identificador da métrica, uma
 * dimensão de baixa cardinalidade e a quantidade.
 */
interface AggregateStorageInterface {

  /**
   * Soma uma quantidade ao contador do dia UTC atual.
   *
   * @param string $metric
   *   Identificador do catálogo, por exemplo `links.link.clicks`.
   * @param string $dimension
   *   Dimensão opcional, por exemplo o identificador do destino. Vazio quando
   *   a métrica não tem recorte.
   * @param int $amount
   *   Quantidade positiva a somar.
   */
  public function record(string $metric, string $dimension = '', int $amount = 1): void;

  /**
   * Substitui os totais de uma métrica num dia, em vez de somar.
   *
   * Usado por conciliações que recalculam um dia a partir da fonte. Linhas do
   * dia que não estão em `$totals` são removidas; totais zero não são gravados.
   *
   * @param string $metric
   *   Identificador do catálogo.
   * @param int $day
   *   Dia UTC (número de dias desde 1970-01-01).
   * @param array<string, int> $totals
   *   Total por dimensão. Vazio zera o dia.
   */
  public function replaceDay(string $metric, int $day, array $totals): void;

  /**
   * Soma uma métrica num intervalo de dias UTC, inclusive.
   *
   * @param string $metric
   *   Identificador do catálogo.
   * @param int $fromDay
   *   Primeiro dia (número de dias desde 1970-01-01 UTC).
   * @param int $toDay
   *   Último dia.
   * @param string|null $dimension
   *   Recorte. NULL soma todas as dimensões.
   */
  public function total(string $metric, int $fromDay, int $toDay, ?string $dimension = NULL): int;

  /**
   * Conta as linhas de uma métrica anteriores ao dia informado.
   *
   * Usado pelo painel antes de confirmar a redução de prazo.
   */
  public function countBefore(string $metric, int $day): int;

  /**
   * Remove as linhas de uma métrica anteriores ao dia informado.
   *
   * @return int
   *   Quantidade de linhas removidas.
   */
  public function purgeMetricBefore(string $metric, int $day): int;

  /**
   * Dimensões com linha registrada para a métrica no dia informado.
   *
   * @return string[]
   *   Dimensões distintas, ordenadas.
   */
  public function dimensions(string $metric, int $day): array;

  /**
   * Número do dia UTC atual, usado como chave de agregação.
   */
  public function currentDay(): int;

}
