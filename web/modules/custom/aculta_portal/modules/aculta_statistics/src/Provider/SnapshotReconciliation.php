<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Provider;

use Drupal\aculta_portal\Statistics\MetricProviderInterface;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Psr\Log\LoggerInterface;

/**
 * Compara retratos gravados com o valor atual calculado na fonte.
 *
 * Dry-run por padrão. A gravação substitui o dia (`replaceDay`) em vez de
 * somar, de modo que reexecutar a reconciliação produz o mesmo estado.
 * Períodos sem coleta aparecem como `missing`; valor indisponível na fonte
 * aparece como `unavailable` e não é convertido em zero.
 */
final class SnapshotReconciliation {

  /**
   * Lock compartilhado entre execuções.
   */
  public const LOCK_NAME = 'aculta_statistics.snapshot_reconciliation';

  /**
   * Intervalo máximo aceito numa execução, em dias.
   */
  public const MAX_DAYS = 90;

  /**
   * Providers do container.
   *
   * @var \Drupal\aculta_portal\Statistics\MetricProviderInterface[]
   */
  private readonly array $providers;

  /**
   * @param iterable<\Drupal\aculta_portal\Statistics\MetricProviderInterface> $providers
   *   Provedores de métricas derivadas.
   */
  public function __construct(
    iterable $providers,
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
  ) {
    $this->providers = is_array($providers) ? array_values($providers) : iterator_to_array($providers, FALSE);
  }

  /**
   * Executa a reconciliação.
   *
   * @param int $fromDay
   *   Primeiro dia UTC.
   * @param int $toDay
   *   Último dia UTC.
   * @param bool $apply
   *   FALSE para dry-run; TRUE grava os valores calculados.
   * @param int $limit
   *   Máximo de comparações nesta execução.
   *
   * @return array<string, mixed>
   *   Relatório estruturado da execução.
   */
  public function run(int $fromDay, int $toDay, bool $apply = FALSE, int $limit = 1000): array {
    if ($fromDay < 0 || $toDay < $fromDay || ($toDay - $fromDay + 1) > self::MAX_DAYS) {
      throw new \InvalidArgumentException('Intervalo de reconciliação inválido; use até 90 dias.');
    }
    if ($limit < 1) {
      throw new \InvalidArgumentException('O limite deve ser positivo.');
    }

    $report = [
      'status' => $apply ? 'applied' : 'dry_run',
      'checked' => 0,
      'matched' => 0,
      'divergent' => 0,
      'missing' => 0,
      'unavailable' => 0,
      'updated' => 0,
      'entries' => [],
    ];

    if (!$this->lock->acquire(self::LOCK_NAME, 900)) {
      $report['status'] = 'locked';
      return $report;
    }

    try {
      foreach ($this->providers as $provider) {
        foreach ($provider->provides() as $metric) {
          if (!$this->catalog->has($metric)) {
            continue;
          }
          $definition = $this->catalog->get($metric);
          if (!$definition->isDerivedFromSource() || $definition->isAdditive()) {
            continue;
          }
          for ($day = $fromDay; $day <= $toDay; $day++) {
            if ($report['checked'] >= $limit) {
              break 3;
            }
            $report['checked']++;
            try {
              $computed = $provider->value($metric, $day);
            }
            catch (\Throwable $exception) {
              $this->logger->error('Reconciliação de @metric falhou: @message', [
                '@metric' => $metric,
                '@message' => $exception->getMessage(),
              ]);
              $computed = NULL;
            }

            if ($computed === NULL) {
              $report['unavailable']++;
              $report['entries'][] = ['metric' => $metric, 'day' => $day, 'stored' => NULL, 'computed' => NULL, 'status' => 'unavailable'];
              continue;
            }

            $hasStored = $this->storage->hasDay($metric, $day);
            $stored = $hasStored ? $this->storage->total($metric, $day, $day) : NULL;
            if (!$hasStored) {
              $report['missing']++;
              $status = 'missing';
            }
            elseif ($stored === $computed) {
              $report['matched']++;
              $status = 'match';
            }
            else {
              $report['divergent']++;
              $status = 'diff';
            }
            $report['entries'][] = ['metric' => $metric, 'day' => $day, 'stored' => $stored, 'computed' => $computed, 'status' => $status];

            if ($apply && $status !== 'match') {
              $this->storage->replaceDay($metric, $day, ['' => $computed]);
              $report['updated']++;
            }
          }
        }
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Reconciliação de retratos interrompida: @message', ['@message' => $exception->getMessage()]);
      $report['status'] = 'failed';
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }

    $this->logger->info('Reconciliação de retratos: @checked verificações, @diff divergências, @updated atualizações.', [
      '@checked' => $report['checked'],
      '@diff' => $report['divergent'] + $report['missing'],
      '@updated' => $report['updated'],
    ]);

    return $report;
  }

}
