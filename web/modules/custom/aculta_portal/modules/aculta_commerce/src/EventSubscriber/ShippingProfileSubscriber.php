<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\EventSubscriber;

use Drupal\commerce_order\Event\OrderProfilesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the shipment shipping profile to the order profiles.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Commerce
 * collects billing and other profile references through this event; without
 * the shipping profile the order refresh would consider the order unshippable
 * and remove its shipments.
 */
final class ShippingProfileSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.profiles' => ['onProfiles'],
    ];
  }

  /**
   * Adds the shipping profile to the order profiles.
   */
  public function onProfiles(OrderProfilesEvent $event): void {
    $order = $event->getOrder();
    if (!$order->hasField('shipments')) {
      return;
    }
    $shipment = reset($order->get('shipments')->referencedEntities());
    if ($shipment && $shipment->getShippingProfile()) {
      $event->addProfile('shipping', $shipment->getShippingProfile());
    }
  }

}
