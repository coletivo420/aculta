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
   *
   * @param \Traversable<string, mixed> $namespaces
   *   O valor informado para $namespaces.
   * @param CacheBackendInterface $cacheBackend
   *   O valor informado para $cacheBackend.
   * @param ModuleHandlerInterface $moduleHandler
   *   O valor informado para $moduleHandler.
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
