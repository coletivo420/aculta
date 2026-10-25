<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\aculta_studio\Attribute\MarleyTab;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Discovers editorial Marley areas in the Plugin/MarleyTab namespace.
 */
final class MarleyTabManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MarleyTab', $namespaces, $module_handler, MarleyTabInterface::class, MarleyTab::class);
    $this->alterInfo('aculta_marley_tab_info');
    $this->setCacheBackend($cache_backend, 'aculta_marley_tab_plugins', ['aculta_marley_tab_plugins']);
  }

}
