<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\PrivateMessageConfigForm;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for PrivateMessageConfigForm plugins.
 *
 * @phpstan-consistent-constructor
 */
abstract class PrivateMessageConfigFormBase extends PluginBase implements PrivateMessageConfigFormPluginInterface {

  /**
   * Tipagem do porte Private Message.
   *
   * @param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): MarkupInterface|string {
    /** @var array<string, mixed> $definition */
    $definition = $this->pluginDefinition;
    /** @var \Drupal\Core\StringTranslation\TranslatableMarkup|string $name */
    $name = $definition['name'];
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
  }

}
