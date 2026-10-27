<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\aculta_portal\Captcha\TurnstileToggle;

/**
 * Barreira de compra: nenhum produto, carrinho ou checkout carrega o Turnstile.
 *
 * O módulo contrib anexa o script do Cloudflare globalmente. Como a política
 * de CAPTCHA é do Portal, removemos o script das rotas compartilhadas por
 * Loja, LMS e Doações. Os formulários comerciais também ficam fora do desafio,
 * mesmo quando o Turnstile estiver ligado no restante do site.
 */
final class ShopCaptchaHooks {

  /**
   * Lists shared product, cart and checkout routes.
   */
  public const COMMERCE_ROUTES = [
    'entity.commerce_product.canonical',
    'commerce_cart.page',
    'commerce_checkout.form',
  ];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Removes a global CAPTCHA element from shared Commerce purchase forms.
   *
   * Captcha's global fallback ignores a disabled point when no base-form
   * point exists. This code-level exception keeps cart and checkout usable
   * even when the site-wide challenge is enabled.
   */
  #[Hook('form_alter', order: new OrderAfter(modules: ['captcha']))]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!$this->isPurchaseForm($form_id)) {
      return;
    }
    $this->removeCaptchaElements($form);
  }

  /**
   * Determines whether a form belongs to a purchase flow.
   */
  private function isPurchaseForm(string $form_id): bool {
    return TurnstileToggle::isCommercePurchaseForm($form_id);
  }

  /**
   * Recursively removes CAPTCHA render elements.
   */
  private function removeCaptchaElements(array &$element): void {
    foreach ($element as $key => &$child) {
      if (!is_array($child)) {
        continue;
      }
      if (($child['#type'] ?? NULL) === 'captcha') {
        unset($element[$key]);
        continue;
      }
      $this->removeCaptchaElements($child);
    }
  }

  /**
   * Removes the global Turnstile script from commerce purchase pages.
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    if (!in_array($this->routeMatch->getRouteName(), self::COMMERCE_ROUTES, TRUE)) {
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
