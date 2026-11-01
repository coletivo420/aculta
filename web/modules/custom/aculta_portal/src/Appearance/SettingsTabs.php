<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Appearance;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;

/**
 * Abas de Minha conta > Configurações. O Portal tem a aba "Modo de cor"; submódulos acrescentam as suas
 * pelo alter aculta_portal_settings_tabs (ex.: aculta_user adiciona "Privacidade").
 */
final class SettingsTabs {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Monta a navegação em abas, com a aba ativa marcada (aria-current e is-active).
   */
  public function build(string $active): array {
    $tabs = [
      'color' => ['title' => (string) t('Modo de cor'), 'route' => 'aculta_portal.account_settings'],
    ];
    $this->moduleHandler->alter('aculta_portal_settings_tabs', $tabs);

    $links = [];
    foreach ($tabs as $key => $tab) {
      $link = PortalLinkElement::build($tab['title'], Url::fromRoute($tab['route']), ['data-portal-account-data-link' => 'true', 'data-portal-ui' => 'account-data-link']);
      if ($key === $active) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-data-nav', 'aria-label' => (string) t('Seções de configurações'), 'data-portal-account-data-nav' => 'true'],
      'links' => $links,
    ];
  }

}
