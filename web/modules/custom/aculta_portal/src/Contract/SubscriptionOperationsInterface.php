<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Contract;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;

/**
 * Performs optional provider operations on a recurring subscription.
 */
interface SubscriptionOperationsInterface {

  /**
   * Returns supported action identifiers and their translated labels.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Keys are action identifiers (`pausar`, `reativar`, `cancelar`).
   */
  public function availableActions(): array;

  /**
   * Checks whether the requested action is allowed for this order.
   */
  public function canChange(OrderInterface $order, string $action): bool;

  /**
   * Applies an action remotely and records the resulting status on the order.
   *
   * @throws \InvalidArgumentException
   *   The action is unknown or disallowed for the current subscription status.
   * @throws \Drupal\aculta_portal\Exception\PaymentOperationException
   *   The provider operation failed.
   */
  public function change(
    PaymentGatewayInterface $gateway,
    OrderInterface $order,
    string $action,
  ): void;

}
