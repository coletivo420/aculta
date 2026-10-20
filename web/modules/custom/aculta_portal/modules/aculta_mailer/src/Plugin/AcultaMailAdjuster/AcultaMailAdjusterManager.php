<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailAdjuster;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Gerenciador dos ajustes de política (plugins `AcultaMailAdjuster`).
 */
final class AcultaMailAdjusterManager extends DefaultPluginManager {

  /**
   * Constructor.
   */
  public function __construct(\Traversable $namespaces, ModuleHandlerInterface $moduleHandler, CacheBackendInterface $cacheBackend) {
    parent::__construct(
      'Plugin/AcultaMailAdjuster',
      $namespaces,
      $moduleHandler,
      AcultaMailAdjusterInterface::class,
      AcultaMailAdjuster::class,
    );
    $this->alterInfo('aculta_mailer_adjuster_info');
    $this->setCacheBackend($cacheBackend, 'aculta_mailer_adjusters');
  }

}
