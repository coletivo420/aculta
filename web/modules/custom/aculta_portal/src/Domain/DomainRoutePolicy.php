<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

/**
 * Centralizes route-family ownership rules shared across Portal boundaries.
 */
final class DomainRoutePolicy {

  /**
   * Returns TRUE for cart/checkout/payment routes of the store, and for the payment return and notification routes.
   * These always belong to MAIN. Donation and subscription routes are not here (see isDonationFlowRouteName).
   */
  public static function isCentralTransactionRouteName(string $routeName): bool {
    return str_starts_with($routeName, 'commerce_cart.')
      || str_starts_with($routeName, 'commerce_checkout.')
      || str_starts_with($routeName, 'commerce_payment.checkout.')
      || $routeName === 'commerce_payment.notify';
  }

  /**
   * Returns TRUE for the donation and subscription flow (aculta_donation). Decisão do responsável (2026-10-17): o fluxo
   * inteiro de apoio e assinatura roda no purpose SUPPORT. Só o gateway (retorno e notificação) fica no MAIN.
   */
  public static function isDonationFlowRouteName(string $routeName): bool {
    return str_starts_with($routeName, 'aculta_donation.');
  }

  /**
   * Propósito de uma rota de doação: a escolha do valor (entrada e valores) é do SUPPORT; as etapas de checkout
   * (formulário do pedido) são rotas do Commerce e ficam no MAIN.
   */
  public static function purposeForDonationRoute(string $routeName): string {
    return $routeName === 'aculta_donation.donation_controller_formPage' ? 'main' : 'support';
  }

  /** Returns TRUE for private transaction routes that must not be indexed. */
  public static function isTransactionalSeoRouteName(string $routeName): bool {
    return self::isCentralTransactionRouteName($routeName)
      || self::isDonationFlowRouteName($routeName)
      || str_starts_with($routeName, 'commerce_payment.')
      || str_starts_with($routeName, 'entity.commerce_order.');
  }

}
