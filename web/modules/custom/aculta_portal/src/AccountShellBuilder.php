<?php

declare(strict_types=1);

namespace Drupal\aculta_portal;

use Drupal\aculta_portal\Domain\DomainPurposeManager;

use Drupal\Component\Utility\Html;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Builds the shared, personalized account shell.
 */
final class AccountShellBuilder {

  public function __construct(
    private readonly CurrentRouteMatch $routeMatch,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly TranslationInterface $translation,
    private readonly DomainPurposeManager $domainPurposeManager,
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
      'aculta_portal.dashboard' => ['title' => $this->translation->translate('Visão geral'), 'route' => 'aculta_portal.dashboard', 'icon' => 'bi-grid'],
      'aculta_portal.support_my' => ['title' => $this->translation->translate('Meu Apoio'), 'route' => 'aculta_portal.support_my', 'icon' => 'bi-heart'],
      'aculta_portal.account_courses' => ['title' => $this->translation->translate('Cursos'), 'route' => 'aculta_portal.account_courses', 'icon' => 'bi-mortarboard'],
      'aculta_portal.account_orders' => ['title' => $this->translation->translate('Meus pedidos'), 'route' => 'aculta_portal.account_orders', 'icon' => 'bi-bag'],
      'aculta_portal.account_payments' => ['title' => $this->translation->translate('Meus pagamentos'), 'route' => 'aculta_portal.account_payments', 'icon' => 'bi-credit-card'],
      'aculta_portal.my_data' => ['title' => $this->translation->translate('Meus Dados'), 'route' => 'aculta_portal.my_data', 'icon' => 'bi-person-vcard'],
      'aculta_portal.connections' => ['title' => $this->translation->translate('Conexões'), 'route' => 'aculta_portal.connections', 'icon' => 'bi-link-45deg'],
      'aculta_portal.security' => ['title' => $this->translation->translate('Segurança'), 'route' => 'aculta_portal.security', 'icon' => 'bi-shield-lock'],
      'aculta_portal.account_settings' => ['title' => $this->translation->translate('Configurações'), 'route' => 'aculta_portal.account_settings', 'icon' => 'bi-sliders'],
    ];
    if (!isset($sections[$route])) {
      return;
    }

    $items = [];
    foreach ($sections as $section_route => $section) {
      $items[] = $this->menuItem(
        $this->sectionUrl($section['route'], $section_route),
        (string) $section['title'],
        $section['icon'],
        $route === $section_route,
      );
    }

    // Reuse Drupal's dynamic logout link to retain its session-bound CSRF URL.
    $logout = $this->menuLinkManager->createInstance('user.logout');
    $items[] = $this->menuItem(
      $logout->getUrlObject()->toString(),
      (string) $this->translation->translate('Sair'),
      'bi-box-arrow-right',
      FALSE,
      ['portal-account__logout'],
    );

    $accordion = '';
    foreach ($sections as $section_route => $section) {
      $accordion .= $this->accordionItem(
        $this->sectionUrl($section['route'], $section_route),
        (string) $section['title'],
        $section['icon'],
        $route === $section_route,
      );
    }
    $logoutUrl = $this->menuLinkManager->createInstance('user.logout')->getUrlObject()->toString();
    $accordion .= $this->logoutRow($logoutUrl);

    $variables['page']['content'] = [
      '#theme' => 'aculta_portal_shell',
      '#menu_accordion' => ['#markup' => '<div class="portal-account__accordion">' . $accordion . '</div>'],
      '#section_title' => $sections[$route]['title'],
      '#menu' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['portal-account__menu']],
      ],
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

  /**
   * Item do menu da conta. Mesmo link em todas as larguras: no celular ganha ícone, rótulo e seta
   * (ver CSS de mobile); no desktop os ícones e a seta ficam ocultos.
   *
   * @param string[] $extraClasses
   */
  private function menuItem(string $href, string $label, string $icon, bool $active, array $extraClasses = []): array {
    $classes = array_merge(['portal-account__link'], $extraClasses);
    $attributes = ' class="' . Html::escape(implode(' ', $classes) . ($active ? ' is-active' : '')) . '"'
      . ' href="' . Html::escape($href) . '" data-aculta-portal-link="true"'
      . ($active ? ' aria-current="page"' : '');
    $markup = '<a' . $attributes . '>'
      . '<i class="bi ' . Html::escape($icon) . ' portal-account__icon" aria-hidden="true"></i>'
      . '<span class="portal-account__label">' . Html::escape($label) . '</span>'
      . '<i class="bi bi-chevron-right portal-account__chevron" aria-hidden="true"></i>'
      . '</a>';
    return ['#markup' => $markup];
  }

  /**
   * Item em sanfona (celular). O painel começa vazio e carrega o conteúdo da seção por AJAX quando é aberto
   * (ver account-navigation.js). A seção atual já vem aberta.
   */
  private function accordionItem(string $href, string $label, string $icon, bool $active): string {
    $open = $active ? ' open' : '';
    return '<details class="portal-account__item"' . $open . '>'
      . '<summary class="portal-account__summary">'
      . '<i class="bi ' . Html::escape($icon) . ' portal-account__icon" aria-hidden="true"></i>'
      . '<span class="portal-account__label">' . Html::escape($label) . '</span>'
      . '<i class="bi bi-chevron-right portal-account__chevron" aria-hidden="true"></i>'
      . '</summary>'
      . '<div class="portal-account__panel" data-portal-account-accordion-panel data-src="' . Html::escape($href) . '">'
      . '<p class="portal-account__panel-loading">Carregando…</p>'
      . '</div>'
      . '</details>';
  }

  /** Sair (celular): linha simples, sem painel. */
  private function logoutRow(string $href): string {
    return '<a class="portal-account__logout-row portal-account__summary" href="' . Html::escape($href) . '">'
      . '<i class="bi bi-box-arrow-right portal-account__icon" aria-hidden="true"></i>'
      . '<span class="portal-account__label">' . Html::escape((string) $this->translation->translate('Sair')) . '</span>'
      . '<i class="bi bi-chevron-right portal-account__chevron" aria-hidden="true"></i>'
      . '</a>';
  }

}
