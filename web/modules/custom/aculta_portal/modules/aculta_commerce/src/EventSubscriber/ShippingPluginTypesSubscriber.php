<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\EventSubscriber;

use Drupal\commerce\Event\CommerceEvents;
use Drupal\commerce\Event\ReferenceablePluginTypesEvent;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the ACULTA shipping method plugin type as referenceable.
 *
 * The commerce_plugin_item field type derives one field type per registered
 * plugin type. Without this registration the aculta_shipping_method plugin
 * field cannot be installed.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
final class ShippingPluginTypesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CommerceEvents::REFERENCEABLE_PLUGIN_TYPES => 'onReferenceablePluginTypes',
    ];
  }

  /**
   * Adds the ACULTA shipping method plugin type.
   */
  public function onReferenceablePluginTypes(ReferenceablePluginTypesEvent $event): void {
    $plugin_types = $event->getPluginTypes();
    $plugin_types['aculta_shipping_method'] = new TranslatableMarkup('ACULTA shipping method');
    $event->setPluginTypes($plugin_types);
  }

}
