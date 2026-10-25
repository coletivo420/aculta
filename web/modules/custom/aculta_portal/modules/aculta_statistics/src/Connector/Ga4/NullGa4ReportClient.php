<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\Ga4;

/**
 * Cliente desligado: não faz pedido nenhum. É o padrão enquanto a propriedade GA4 e a credencial não existem.
 */
final class NullGa4ReportClient implements Ga4ReportClient {

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function report(int $fromDay, int $toDay): ?array {
    return NULL;
  }

}
