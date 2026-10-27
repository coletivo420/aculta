<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

use Drupal\commerce_checkout\Entity\CheckoutFlowInterface;
use Drupal\commerce_checkout\Resolver\ChainCheckoutFlowResolverInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Resolves a cart's first valid checkout step for the current session user.
 */
final class CheckoutStepResolver {

  public function __construct(private readonly ChainCheckoutFlowResolverInterface $checkoutFlowResolver) {}

  /**
   * Returns the saved step when visible, or the first visible step.
   *
   * Commerce stores a cart's checkout step across requests. A step such as
   * `login` can become invisible after the customer signs in, so it must not be
   * reused as the initial destination for that authenticated session.
   */
  public function resolve(OrderInterface $order): string {
    $checkout_flow = $order->get('checkout_flow')->entity;
    if (!$checkout_flow instanceof CheckoutFlowInterface) {
      // Resolve the flow without using CheckoutOrderManager::getCheckoutFlow(),
      // which saves the order as a side effect when the field is empty. Cart
      // rendering only needs a URL and must remain read-only.
      $checkout_flow = $this->checkoutFlowResolver->resolve($order);
    }
    if (!$checkout_flow instanceof CheckoutFlowInterface) {
      return 'login';
    }
    $flow = $checkout_flow->getPlugin()->setOrder($order);
    $visible_steps = $flow->getVisibleSteps();
    $stored_step = $order->get('checkout_step')->value;

    return self::selectVisibleStep(
      is_string($stored_step) ? $stored_step : NULL,
      $visible_steps,
    ) ?? 'login';
  }

  /**
   * Selects a saved step only when it is currently visible.
   *
   * Otherwise, starts at the first visible step.
   *
   * @param string|null $stored_step
   *   The step saved on the order.
   * @param array<string, mixed> $visible_steps
   *   Checkout flow visible steps keyed by step ID.
   */
  public static function selectVisibleStep(
    ?string $stored_step,
    array $visible_steps,
  ): ?string {
    if ($stored_step !== NULL && array_key_exists($stored_step, $visible_steps)) {
      return $stored_step;
    }
    $first_step = array_key_first($visible_steps);
    return is_string($first_step) ? $first_step : NULL;
  }

}
