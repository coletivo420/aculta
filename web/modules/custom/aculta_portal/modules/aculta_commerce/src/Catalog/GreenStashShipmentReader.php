<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Catalog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Read-only shipment listing for the Green Stash shipping area.
 *
 * The operator sees the operational state of the store's shipments without
 * links to native Commerce admin forms. Only aggregate logistics data is
 * exposed; no customer addresses, e-mails or payment data.
 */
final class GreenStashShipmentReader {

  /**
   * Constructs a new GreenStashShipmentReader object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Lists shipments visible to the operator.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param int $before
   *   The last shipment ID of the previous page, or 0 for the first page.
   * @param int $limit
   *   The page size.
   *
   * @return array{items: array<int, array<string, string|int|null>>, next: int|null}
   *   The shipment rows and the next cursor.
   */
  public function page(AccountInterface $account, int $before = 0, int $limit = 25): array {
    $limit = max(1, min($limit, 50));
    if (!$account->hasPermission('administer aculta shipments')) {
      return ['items' => [], 'next' => NULL];
    }
    $storage = $this->entityTypeManager->getStorage('aculta_shipment');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('shipment_id', 'DESC')
      ->range(0, $limit + 1);
    if ($before > 0) {
      $query->condition('shipment_id', $before, '<');
    }
    $ids = $query->execute();
    $next = NULL;
    if (count($ids) > $limit) {
      $ids = array_slice($ids, 0, $limit);
      $next = (int) end($ids);
    }
    $shipments = $storage->loadMultiple($ids);

    $items = [];
    foreach ($shipments as $shipment) {
      $order = $shipment->getOrder();
      $amount = $shipment->getAmount();
      $items[] = [
        'id' => (int) $shipment->id(),
        'order_number' => $order?->getOrderNumber() ?: '—',
        'state' => (string) $shipment->getState()->getId(),
        'service' => $shipment->getShippingServiceLabel() ?: 'Sem serviço selecionado',
        'amount' => $amount ? $amount->getNumber() : NULL,
        'currency' => $amount ? $amount->getCurrencyCode() : NULL,
        'tracking_code' => $shipment->getTrackingCode() ?: NULL,
        'created' => (int) $shipment->getCreatedTime(),
      ];
    }

    return ['items' => $items, 'next' => $next];
  }

}
