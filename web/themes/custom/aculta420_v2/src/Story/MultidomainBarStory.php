<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\aculta_messenger\Hook\MessengerBarHooks;
use Drupal\aculta_portal\Hook\PortalHooks;
use Drupal\ui_patterns_library\StoryDefault;

/**
 * Story da barra institucional com os dados reais da página.
 *
 * Os valores vêm dos mesmos hooks de preprocess_page usados pela página
 * (PortalHooks e MessengerBarHooks), para o usuário atual. O mapeamento de nomes
 * segue o include de layout/page.html.twig; se o include mudar, este mapeamento
 * precisa acompanhar.
 */
final class MultidomainBarStory extends StoryDefault {

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    $vars = $this->pageVariables();
    $cart = $vars['aculta_cart'] ?? [];
    $messenger = $vars['aculta_messenger'] ?? [];
    $alerts = $vars['aculta_alerts'] ?? [];
    $layout = $vars['domain_presentation']['layout'] ?? [];
    $themePath = \Drupal::service('extension.list.theme')->getPath('aculta420_v2');

    return [
      'brand_home_url' => (string) ($vars['aculta_brand_home'] ?? '/'),
      'logo_base_url' => base_path() . $themePath . '/assets/logos',
      'logo_alt' => 'ACULTA - Associação Cultural Antiproibicionista',
      'is_account_domain' => (bool) ($layout['institution_bar_account'] ?? FALSE),
      'show_utility_bar' => (bool) ($layout['show_utility_bar'] ?? TRUE),
      'cart_url' => $cart['url'] ?? NULL,
      'cart_count' => (int) ($cart['count'] ?? 0),
      'cart_empty' => (bool) ($cart['empty'] ?? TRUE),
      'cart_total' => (string) ($cart['total'] ?? ''),
      'cart_sites' => $cart['sites'] ?? [],
      'cart_checkout_url' => (string) ($cart['checkout_url'] ?? ''),
      'messages_url' => $messenger['url'] ?? NULL,
      'messages_count' => (int) ($messenger['unread_count'] ?? 0),
      'messages_all_url' => (string) ($messenger['all_url'] ?? ''),
      'alerts_url' => $alerts['url'] ?? NULL,
      'alerts_count' => (int) ($alerts['count'] ?? 0),
      'alerts_count_kind' => (string) ($alerts['count_kind'] ?? 'active'),
      'alerts_all_url' => (string) ($alerts['all_url'] ?? ''),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    $vars = $this->pageVariables();
    return array_filter([
      'search' => $vars['aculta_search_utility'] ?? NULL,
      'account_menu' => $vars['aculta_account_menu'] ?? NULL,
    ]);
  }

  /**
   * Variáveis de página produzidas pelos hooks reais, para o usuário atual.
   *
   * @return array<string, mixed>
   */
  private function pageVariables(): array {
    $vars = ['page' => []];
    \Drupal::service(PortalHooks::class)->preprocessPage($vars);
    \Drupal::service(MessengerBarHooks::class)->preprocessPage($vars);
    return $vars;
  }

}
