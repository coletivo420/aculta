<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\aculta_portal\Cleanup\FileReferenceInspector;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the real Portal cleanup services without enabling its full module.
 *
 * H5P kernel tests cover isolated integrations. The Portal module has a much
 * larger dependency closure, so this exposes only its production cleanup
 * services and their real Drupal/Image dependencies to these test containers.
 */
trait PortalCleanupKernelServicesTrait {

  /**
   * {@inheritdoc}
   */
  protected function registerPortalCleanupServices(ContainerBuilder $container): void {
    $container->setDefinition('aculta_portal.file_reference_inspector', new Definition(
      FileReferenceInspector::class,
      [new Reference('entity_type.manager'), new Reference('entity_field.manager'), new Reference('database')],
    ));
    $container->setDefinition('aculta_portal.cleanup.logger', (new Definition(
      LoggerChannelInterface::class,
      ['aculta_portal_cleanup'],
    ))->setFactory([new Reference('logger.factory'), 'get']));
    $container->setDefinition('aculta_portal.unused_file_cleaner', new Definition(
      UnusedFileCleaner::class,
      [
        new Reference('file.usage'),
        new Reference('database'),
        new Reference('aculta_portal.file_reference_inspector'),
        new Reference('aculta_image.file_remover'),
        new Reference('aculta_image.inspector'),
        new Reference('aculta_portal.cleanup.logger'),
      ],
    ));
  }

}
