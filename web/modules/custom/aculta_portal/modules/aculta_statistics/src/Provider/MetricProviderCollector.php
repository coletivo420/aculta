<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Provider;

use Drupal\aculta_portal\Statistics\MetricProviderInterface;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Catalog\MetricDefinition;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * Coleta valores calculados na fonte e grava o retrato do dia.
 *
 * Provedores são opcionais: sem nenhum, a coleta é vazia. O valor substitui o
 * dia (`replaceDay`) em vez de somar, preservando a semântica de snapshot,
 * janela distinta e distribuição. O histórico já gravado não é reescrito em
 * dias anteriores, exceto por reconciliação explícita.
 */
final class MetricProviderCollector {

  /**
   * Providers ordenados pela tag do container.
   *
   * @var \Drupal\aculta_portal\Statistics\MetricProviderInterface[]
   */
  private readonly array $providers;

  /**
   * @param iterable<\Drupal\aculta_portal\Statistics\MetricProviderInterface> $providers
   *   Provedores registrados por módulos donos de dados.
   */
  public function __construct(
    iterable $providers,
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
    private readonly LoggerInterface $logger,
  ) {
    $this->providers = is_array($providers) ? array_values($providers) : iterator_to_array($providers, FALSE);
  }

  /**
   * Coleta e grava os valores do dia.
   *
   * @param int|null $day
   *   Dia UTC; NULL usa o dia corrente.
   *
   * @return array<string, int>
   *   Métricas gravadas e respectivos valores.
   */
  public function collect(?int $day = NULL): array {
    $day ??= $this->storage->currentDay();
    $collected = [];
    foreach ($this->providers as $provider) {
      foreach ($provider->provides() as $metric) {
        if (!$this->catalog->has($metric)) {
          $this->logger->warning('Provedor de métricas oferece identificador fora do catálogo: @metric.', ['@metric' => $metric]);
          continue;
        }
        $definition = $this->catalog->get($metric);
        if (!$this->isCollectable($definition)) {
          continue;
        }
        try {
          $value = $provider->value($metric, $day);
        }
        catch (\Throwable $exception) {
          $this->logger->error('Falha ao calcular @metric: @message', [
            '@metric' => $metric,
            '@message' => $exception->getMessage(),
          ]);
          continue;
        }
        if ($value === NULL || $value < 0) {
          continue;
        }
        $this->storage->replaceDay($metric, $day, ['' => $value]);
        $collected[$metric] = $value;
      }
    }
    return $collected;
  }

  /**
   * Indica métrica calculada na fonte e gravável como retrato do dia.
   */
  private function isCollectable(MetricDefinition $definition): bool {
    return $definition->isDerivedFromSource()
      && $definition->type !== MetricDefinition::TYPE_RATIO;
  }

}
