<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Contract;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Reads subscription and charge status through an optional payment provider.
 */
interface SubscriptionStatusInterface {

  /**
   * Returns the persisted order bundle used for recurring subscriptions.
   */
  public function subscriptionOrderType(): string;

  /**
   * Returns the status used when a subscription has no recorded status.
   */
  public function pendingStatus(): string;

  /**
   * Returns statuses in the order used by the subscription report.
   *
   * @return list<string>
   *   Status identifiers in display order.
   */
  public function orderedStatuses(): array;

  /**
   * Returns the persisted subscription status for an order, or an empty string.
   */
  public function statusForOrder(OrderInterface $order): string;

  /**
   * Returns the persisted status of the latest charge, or an empty string.
   */
  public function latestChargeStatus(OrderInterface $order): string;

  /**
   * Returns a translated label for a subscription status.
   */
  public function statusLabel(string $status): TranslatableMarkup;

  /**
   * Returns a translated label for a charge status.
   */
  public function chargeStatusLabel(string $status): TranslatableMarkup;

}
