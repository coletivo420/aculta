<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\Ga4;

/**
 * Consulta ao GA4 Data API (0.6.0). Somente leitura: nunca envia dado de visitante ao Google.
 *
 * Enquanto não houver propriedade configurada e credencial no arquivo de segredos, a implementação é a nula
 * (`NullGa4ReportClient`), e nenhum pedido sai do servidor.
 */
interface Ga4ReportClient {

  /**
   * Indica se a integração está ligada e configurada.
   */
  public function isEnabled(): bool;

  /**
   * Relatório agregado do GA4 para um intervalo, ou NULL quando a integração está desligada ou indisponível.
   *
   * @return array<string, mixed>|null
   *   Linhas agregadas do relatório.
   */
  public function report(int $fromDay, int $toDay): ?array;

}
