<?php

declare(strict_types=1);

namespace Drupal\aculta_portal;

use Drupal\aculta_portal\Domain\DomainPurposeManager;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Builds the shared, personalized account shell.
 */
final class AccountShellBuilder {

  /** Subrotas da conta e a seção do menu que as contém. */
  private const PARENT_SECTION = [
    'aculta_portal.my_data_address' => 'aculta_portal.my_data',
    'aculta_portal.my_data_other' => 'aculta_portal.my_data',
    'commerce_order.address_book.overview' => 'aculta_portal.my_data',
    'entity.commerce_order.user_view' => 'view.commerce_user_orders.order_page',
    'aculta_portal.security_password' => 'aculta_portal.security',
  ];

  public function __construct(
    private readonly CurrentRouteMatch $routeMatch,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly TranslationInterface $translation,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly ?RouteProviderInterface $routeProvider = NULL,
  ) {}

  /**
   * Link de uma seção. A visão geral é a raiz do Domain ACCOUNT (`/`); o caminho técnico nunca aparece no menu.
   */
  private function sectionUrl(string $route, string $sectionRoute): string {
    if ($sectionRoute === 'aculta_portal.dashboard') {
      return ($this->domainPurposeManager->pathUrl('account', '/') ?? Url::fromRoute('aculta_portal.dashboard'))->toString();
    }
    return Url::fromRoute($route)->toString();
  }

  /**
   * Wraps an existing route's render array in the shared account shell.
   */
  public function build(array &$variables): void {
    $route = $this->routeMatch->getRouteName();
    $sections = [
      'aculta_portal.dashboard' => ['title' => $this->translation->translate('Visão geral'), 'route' => 'aculta_portal.dashboard', 'icon' => 'grid'],
      'aculta_portal.support_my' => ['title' => $this->translation->translate('Meu Apoio'), 'route' => 'aculta_portal.support_my', 'icon' => 'heart'],
      'aculta_portal.account_courses' => ['title' => $this->translation->translate('Cursos'), 'route' => 'aculta_portal.account_courses', 'icon' => 'mortarboard'],
      ...($this->routeExists('aculta_user.atividade') ? ['aculta_user.atividade' => ['title' => $this->translation->translate('Atividade'), 'route' => 'aculta_user.atividade', 'icon' => 'activity']] : []),
      'view.commerce_user_orders.order_page' => ['title' => $this->translation->translate('Meus pedidos'), 'route' => 'view.commerce_user_orders.order_page', 'icon' => 'bag'],
      'aculta_portal.my_data' => ['title' => $this->translation->translate('Meus Dados'), 'route' => 'aculta_portal.my_data', 'icon' => 'person-vcard'],
      'aculta_portal.connections' => ['title' => $this->translation->translate('Conexões'), 'route' => 'aculta_portal.connections', 'icon' => 'link-45deg'],
      'aculta_portal.security' => ['title' => $this->translation->translate('Segurança'), 'route' => 'aculta_portal.security', 'icon' => 'shield-lock'],
      'aculta_portal.account_settings' => ['title' => $this->translation->translate('Configurações'), 'route' => 'aculta_portal.account_settings', 'icon' => 'sliders'],
    ];
    // Subpáginas herdam a seção do menu (ex.: endereço -> Meus Dados, detalhe do pedido -> Meus pedidos), para que
    // toda página da conta, acessada direto ou pela navegação, renderize dentro do shell com a seção certa ativa.
    $sectionRoute = self::PARENT_SECTION[$route] ?? $route;
    if (!isset($sections[$sectionRoute])) {
      return;
    }
    $route = $sectionRoute;

    // Dados do shell: o tema (ACULTA420) monta o menu e a sanfona. O módulo não entrega marcação nem classes.
    $menuItems = [];
    $accordionItems = [];
    foreach ($sections as $section_route => $section) {
      $href = $this->sectionUrl($section['route'], $section_route);
      $menuItems[] = [
        'href' => $href,
        'label' => (string) $section['title'],
        'icon' => $section['icon'],
        'active' => $route === $section_route,
      ];
      $accordionItems[] = [
        'href' => $href,
        'label' => (string) $section['title'],
        'icon' => $section['icon'],
        'open' => $route === $section_route,
      ];
    }
    // Reuse Drupal's dynamic logout link to retain its session-bound CSRF URL.
    $logoutUrl = $this->menuLinkManager->createInstance('user.logout')->getUrlObject()->toString();
    $logout = ['href' => $logoutUrl, 'label' => (string) $this->translation->translate('Sair')];

    $variables['page']['content'] = [
      '#theme' => 'aculta_portal_shell',
      // Todo shell da conta carrega o comportamento (navegação AJAX, sanfona do celular), inclusive pedidos.
      '#attached' => ['library' => ['aculta_portal/account-navigation']],
      '#section_title' => $sections[$route]['title'],
      '#menu_items' => $menuItems,
      '#accordion_items' => $accordionItems,
      '#logout' => $logout,
      '#content' => $variables['page']['content'] ?? [],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'route'],
        'max-age' => 0,
      ],
    ];
    // The shell owns the only H1. Keep account actions in its sidebar.
    $variables['aculta_page_title'] = [];
    $variables['aculta_account_menu'] = [];
  }


  /** Verdadeiro quando a rota existe (módulo opcional ativo). O Portal não depende do submódulo. */
  private function routeExists(string $name): bool {
    if ($this->routeProvider === NULL) {
      return FALSE;
    }
    try {
      $this->routeProvider->getRouteByName($name);
      return TRUE;
    }
    catch (RouteNotFoundException) {
      return FALSE;
    }
  }

}
