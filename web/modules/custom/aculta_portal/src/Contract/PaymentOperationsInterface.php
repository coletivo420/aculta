<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Contract;

use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Performs optional payment-provider operations on Commerce payments.
 */
interface PaymentOperationsInterface {

  /**
   * Refreshes a payment from its provider and returns the remote status.
   *
   * @throws \DomainException
   *   The payment cannot be queried in its current state or has no provider
   *   ID.
   * @throws \Drupal\aculta_portal\Exception\PaymentOperationException
   *   The provider operation failed.
   */
  public function refresh(PaymentInterface $payment): string;

  /**
   * Cancels a pending payment through its provider.
   *
   * @throws \DomainException
   *   The payment cannot be canceled in its current state.
   * @throws \Drupal\aculta_portal\Exception\PaymentOperationException
   *   The provider operation failed.
   */
  public function cancel(PaymentInterface $payment): void;

}
