<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Retention;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Prazo de retenção de cada métrica, lido da configuração administrável.
 *
 * Opções fixas (ADR-0003): 30, 90, 180, 365 dias ou indefinido. O valor 0 na
 * configuração significa indefinido. Quando a métrica não tem valor salvo, usa
 * o padrão do catálogo.
 */
final class RetentionPolicy {

  /**
   * Valor de configuração que significa "sem prazo".
   */
  public const INDEFINITE = 0;

  /**
   * Prazos aceitos pelo painel, em dias.
   */
  public const OPTIONS = [30, 90, 180, 365, self::INDEFINITE];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MetricCatalog $catalog,
  ) {}

  /**
   * Prazo configurado para a métrica, em dias (0 = indefinido).
   */
  public function daysFor(string $metric): int {
    foreach ($this->configFactory->get('aculta_statistics.settings')->get('retention') ?? [] as $row) {
      if ($row['metric'] === $metric) {
        return (int) $row['days'];
      }
    }
    return $this->catalog->get($metric)->defaultRetentionDays ?? self::INDEFINITE;
  }

  /**
   * Primeiro dia que deve ser mantido, ou NULL quando não há purge.
   *
   * Linhas com `day` menor que o valor retornado são apagadas.
   */
  public function cutoffDay(int $days, int $currentDay): ?int {
    if ($days === self::INDEFINITE) {
      return NULL;
    }
    return $currentDay - $days;
  }

  /**
   * Indica se o prazo aceito pelo painel é válido.
   */
  public function isValidOption(int $days): bool {
    return in_array($days, self::OPTIONS, TRUE);
  }

}
