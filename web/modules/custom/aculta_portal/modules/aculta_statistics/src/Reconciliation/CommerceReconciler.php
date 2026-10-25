<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Reconciliation;

use Drupal\aculta_statistics\EventSubscriber\CommerceOrderPaidSubscriber;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Compara, por dia UTC e moeda, os pedidos concluídos no Commerce com os contadores.
 *
 * Fonte oficial: pedidos no estado `completed` cujo `completed` cai no dia. Os contadores
 * são os de `CommerceOrderPaidSubscriber`. Pedidos anteriores à flag `paid_event_dispatched`
 * do Commerce podem aparecer como diferença; isso é esperado e aparece no relatório.
 */
final class CommerceReconciler {

  private const DAY_SECONDS = 86400;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * Totais oficiais de um dia UTC, por moeda.
   *
   * @return array<string, array{orders: int, cents: int}>
   *   Pedidos e centavos por moeda ISO.
   */
  public function officialTotals(int $day): array {
    $start = $day * self::DAY_SECONDS;
    $ids = $this->entityTypeManager->getStorage('commerce_order')->getQuery()
      ->accessCheck(FALSE)
      ->condition('state', 'completed')
      ->condition('completed', [$start, $start + self::DAY_SECONDS - 1], 'BETWEEN')
      ->execute();

    $totals = [];
    foreach ($this->entityTypeManager->getStorage('commerce_order')->loadMultiple($ids) as $order) {
      $price = $order->getTotalPrice();
      if ($price === NULL) {
        continue;
      }
      $currency = $price->getCurrencyCode();
      $totals[$currency]['orders'] = ($totals[$currency]['orders'] ?? 0) + 1;
      $totals[$currency]['cents'] = ($totals[$currency]['cents'] ?? 0) + (int) round((float) $price->getNumber() * 100);
    }
    return $totals;
  }

  /**
   * Totais contados pelo Statistics para um dia UTC, por moeda.
   *
   * @return array<string, array{orders: int, cents: int}>
   *   Pedidos e centavos por moeda ISO.
   */
  public function countedTotals(int $day): array {
    $totals = [];
    foreach ($this->storage->dimensions(CommerceOrderPaidSubscriber::METRIC_ORDERS, $day) as $currency) {
      $totals[$currency] = [
        'orders' => $this->storage->total(CommerceOrderPaidSubscriber::METRIC_ORDERS, $day, $day, $currency),
        'cents' => $this->storage->total(CommerceOrderPaidSubscriber::METRIC_REVENUE, $day, $day, $currency),
      ];
    }
    return $totals;
  }

  /**
   * Divergências do dia informado (vazio quando tudo bate).
   *
   * @return array<string, array{orders: int, cents: int}>
   *   Diferenças por moeda.
   */
  public function reconcileDay(int $day): array {
    return ReconciliationDiff::compare($this->officialTotals($day), $this->countedTotals($day));
  }

}
