<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\Component\Plugin\PluginBase;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Studio tabs. Dependencies come from the container.
 */
abstract class StudioTabBase extends PluginBase implements StudioTabInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly ModuleHandlerInterface $moduleHandler,
    TranslationInterface $stringTranslation,
    protected readonly DomainPurposeManager $domainPurposes,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * An action that goes to the owner's route.
   *
   * Without a purpose, the route is on this host. With a purpose, the link is the owner's domain (routeUrl) and
   * the access is the owner's route access evaluated in that domain (runInPurpose), never a copy of the rule.
   *
   * @return array{title: \Stringable|string, url: \Drupal\Core\Url, access: bool}
   */
  protected function routeAction(\Stringable|string $title, string $route, array $params, AccountInterface $account, ?string $purpose = NULL): array {
    if ($purpose === NULL) {
      $url = Url::fromRoute($route, $params);
      return ['title' => $title, 'url' => $url, 'access' => $url->access($account)];
    }
    $url = $this->domainPurposes->routeUrl($purpose, $route, $params);
    if ($url === NULL) {
      return ['title' => $title, 'url' => Url::fromRoute('<front>'), 'access' => FALSE];
    }
    $access = $this->domainPurposes->runInPurpose($purpose, static fn (): bool => Url::fromRoute($route, $params)->access($account));
    return ['title' => $title, 'url' => $url, 'access' => (bool) $access];
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
    );
  }

}
