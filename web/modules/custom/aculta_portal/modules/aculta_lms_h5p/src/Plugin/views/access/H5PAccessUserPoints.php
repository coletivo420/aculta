<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Plugin\views\access;

use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\Routing\Route;

/** Access plugin for viewing H5P user points. */
#[ViewsAccess(
  id: 'h5paccessuserpoints',
  title: new TranslatableMarkup('Acesso aos resultados H5P'),
  help: new TranslatableMarkup('A pessoa autenticada pode ver os resultados H5P desta conta?'),
)]
class H5PAccessUserPoints extends AccessPluginBase implements ContainerFactoryPluginInterface {

  protected RouteMatchInterface $currentRouteMatch;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteMatchInterface $currentRouteMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRouteMatch = $currentRouteMatch;
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if ($account->hasPermission('access all h5p results')) {
      return TRUE;
    }

    if (!$account->hasPermission('access own h5p results')
      || $this->currentRouteMatch->getRouteName() !== 'entity.user.canonical') {
      return FALSE;
    }

    $routeAccount = $this->currentRouteMatch->getParameter('user');
    return $routeAccount instanceof AccountInterface
      && (string) $account->id() === (string) $routeAccount->id();
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_access', 'TRUE');
  }
}
