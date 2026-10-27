<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Contract;

/**
 * Reports whether a Commerce payment state can be refunded.
 */
interface RefundPolicyInterface {

  /**
   * Checks whether the payment state permits a refund operation.
   */
  public function isRefundable(string $paymentState): bool;

}
