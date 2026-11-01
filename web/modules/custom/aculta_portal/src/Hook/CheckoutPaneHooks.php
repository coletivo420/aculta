<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\PaymentInformationWithoutAddress;
use Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\AcultaCheckoutLogin;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Ajustes dos panes de checkout do Commerce feitos pelo Portal.
 */
final class CheckoutPaneHooks {

  /**
   * Troca os panes de pagamento e login pelas versões do Portal.
   */
  #[Hook('commerce_checkout_pane_info_alter')]
  public function commerceCheckoutPaneInfoAlter(array &$definitions): void {
    if (isset($definitions['payment_information'])) {
      $definitions['payment_information']['class'] = PaymentInformationWithoutAddress::class;
    }
    if (isset($definitions['login'])) {
      $definitions['login']['class'] = AcultaCheckoutLogin::class;
    }
  }

}
