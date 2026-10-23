<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the independent Green Stash experience in ACULTA Studio.
 */
final class AcultaCommerceServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('aculta_studio.experience_manager')
      && $container->hasDefinition('aculta_commerce.experience.green_stash')) {
      $container->getDefinition('aculta_studio.experience_manager')
        ->addMethodCall('addProvider', [new Reference('aculta_commerce.experience.green_stash')]);
    }
  }

}
