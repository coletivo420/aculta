<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailerAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailerAdjuster;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Gerenciador dos ajustes de política (plugins `AcultaMailerAdjuster`).
 */
final class AcultaMailerAdjusterManager extends DefaultPluginManager {

  /**
   * Constructor.
   */
  public function __construct(\Traversable $namespaces, ModuleHandlerInterface $moduleHandler, CacheBackendInterface $cacheBackend) {
    parent::__construct(
      'Plugin/AcultaMailerAdjuster',
      $namespaces,
      $moduleHandler,
      AcultaMailerAdjusterInterface::class,
      AcultaMailerAdjuster::class,
    );
    $this->alterInfo('aculta_mailer_adjuster_info');
    $this->setCacheBackend($cacheBackend, 'aculta_mailer_adjusters');
  }

}
