<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Barreira da loja: nenhuma página de produto, carrinho ou checkout carrega o Turnstile.
 *
 * O módulo contrib turnstile anexa o script do Cloudflare a todas as páginas. Como a política de CAPTCHA é do
 * Portal, a remoção acontece aqui, só nas rotas da loja. Os formulários da loja ficam fora do CAPTCHA em
 * Captcha\TurnstileToggle (SHOP_FORM_IDS), inclusive quando o responsável liga o Turnstile no resto do site.
 */
final class ShopCaptchaHooks {

  /** Rotas de produto, carrinho e checkout da loja (sem CAPTCHA). */
  public const SHOP_ROUTES = [
    'entity.commerce_product.canonical',
    'commerce_cart.page',
    'commerce_checkout.form',
  ];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    if (!in_array($this->routeMatch->getRouteName(), self::SHOP_ROUTES, true)) {
      return;
    }
    $head = $attachments['#attached']['html_head'] ?? [];
    foreach ($head as $key => $item) {
      $attributes = $item[0]['#attributes'] ?? [];
      $url = (string) ($attributes['src'] ?? $attributes['href'] ?? '');
      if (str_contains($url, 'challenges.cloudflare.com')) {
        unset($attachments['#attached']['html_head'][$key]);
      }
    }
  }

}
