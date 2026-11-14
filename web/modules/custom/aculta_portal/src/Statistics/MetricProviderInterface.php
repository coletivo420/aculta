<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Statistics;

/**
 * Contrato neutro para provedores de métricas calculadas na fonte.
 *
 * Vive no Portal para que um módulo dono de dados (Fórum, Wiki, LMS) possa
 * oferecer indicadores sem depender de `aculta_statistics`. A central apenas
 * coleta o valor já agregado e o grava no armazenamento; o provedor nunca
 * concede acesso a conteúdo e nunca recebe identificadores de pessoa.
 *
 * O tipo da métrica no catálogo determina a leitura: `snapshot`,
 * `distinct-window` e `distribution` não podem ser somados entre dias.
 */
interface MetricProviderInterface {

  /**
   * Identificadores de métricas que este provedor calcula.
   *
   * @return string[]
   *   Identificadores do catálogo central.
   */
  public function provides(): array;

  /**
   * Valor da métrica no dia UTC informado.
   *
   * @param string $metric
   *   Identificador do catálogo.
   * @param int $day
   *   Dia UTC desde 1970-01-01.
   *
   * @return int|null
   *   Valor agregado, ou NULL quando o provedor não consegue medir o dia.
   */
  public function value(string $metric, int $day): ?int;

}
