<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce;

use Drupal\aculta_commerce\Attribute\AcultaShippingMethod;
use Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of ACULTA shipping method plugins.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Uses the
 * aculta_shipping_method plugin type to avoid collisions with the contributed
 * commerce_shipping plugin type during the transition.
 */
class ShippingMethodManager extends DefaultPluginManager {

  /**
   * Constructs a new ShippingMethodManager object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Commerce/ShippingMethod',
      $namespaces,
      $module_handler,
      ShippingMethodInterface::class,
      AcultaShippingMethod::class,
    );
    $this->alterInfo('aculta_shipping_method_info');
    $this->setCacheBackend($cache_backend, 'aculta_shipping_method_plugins');
  }

}
