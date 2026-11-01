<?php

namespace Drupal\aculta_mail_confirmer_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Provides the aculta mail confirmer test service provider.
 */
class AcultaMailConfirmerTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Replace the mail manager with our test mail collector.
    $container->getDefinition('plugin.manager.mail')
      ->setClass(MailCollector::class);
  }

}
