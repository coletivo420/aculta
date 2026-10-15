<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger;

use Drupal\aculta_messenger\Mapper\MessengerPrivateMessageMapper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Replaces the Private Message mapper class: a block keeps the conversation listed for the blocker.
 *
 * The contrib service keeps its id and arguments; only its class changes. See MessengerPrivateMessageMapper.
 */
final class AcultaMessengerServiceProvider implements ServiceProviderInterface {

  public function register(ContainerBuilder $container): void {
    if ($container->hasDefinition('private_message.mapper')) {
      $container->getDefinition('private_message.mapper')->setClass(MessengerPrivateMessageMapper::class);
    }
  }

}
