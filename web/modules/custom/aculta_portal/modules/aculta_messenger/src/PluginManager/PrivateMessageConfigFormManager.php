<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\aculta_messenger\Annotation\PrivateMessageConfigForm as PrivateMessageConfigFormAnnotation;
use Drupal\aculta_messenger\Attribute\PrivateMessageConfigForm as PrivateMessageConfigFormAttribute;
use Drupal\aculta_messenger\Plugin\PrivateMessageConfigForm\PrivateMessageConfigFormPluginInterface;

/**
 * Plugin manager for detect PrivateMessageConfigForm plugins.
 */
class PrivateMessageConfigFormManager extends DefaultPluginManager implements PrivateMessageConfigFormManagerInterface {

  /**
   * Tipagem do porte Private Message.
   * @param array<string, mixed> $namespaces
   * @param array<string, mixed> $namespaces
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct(
      'Plugin/PrivateMessageConfigForm',
      $namespaces,
      $moduleHandler,
      PrivateMessageConfigFormPluginInterface::class,
      PrivateMessageConfigFormAttribute::class,
      PrivateMessageConfigFormAnnotation::class,
    );
    $this->alterInfo('private_message_config_form_info');
    $this->setCacheBackend($cacheBackend, 'private_message_config_form');
  }

}
