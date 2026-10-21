<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\aculta_studio\Attribute\StudioTab;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Discovers the Studio tabs in the Plugin/StudioTab namespace of each module.
 */
final class StudioTabManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/StudioTab', $namespaces, $module_handler, StudioTabInterface::class, StudioTab::class);
    $this->alterInfo('aculta_studio_tab_info');
    $this->setCacheBackend($cache_backend, 'aculta_studio_tab_plugins', ['aculta_studio_tab_plugins']);
  }

}
