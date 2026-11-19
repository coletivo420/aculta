<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\EventSubscriber;

use Drupal\aculta_commerce_melhorenvio\Hook\ShippingCalculatorHooks;
use Drupal\aculta_portal\Event\CartUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Refreshes the optional shipping quote after an HTMX cart update is saved.
 */
final class CartUpdatedSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ShippingCalculatorHooks $shippingCalculatorHooks,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [CartUpdatedEvent::class => 'refreshQuote'];
  }

  /**
   * Recalculates the quote from current cart lines and saved postal code.
   */
  public function refreshQuote(CartUpdatedEvent $event): void {
    $this->shippingCalculatorHooks->refreshAfterCartUpdate();
  }

}
