<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\PaymentInformationWithoutAddress;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Ajustes dos panes de checkout do Commerce feitos pelo Portal.
 */
final class CheckoutPaneHooks {

  /**
   * Troca a classe do pane de pagamento pela versão que não repete o endereço no resumo.
   */
  #[Hook('commerce_checkout_pane_info_alter')]
  public function commerceCheckoutPaneInfoAlter(array &$definitions): void {
    if (isset($definitions['payment_information'])) {
      $definitions['payment_information']['class'] = PaymentInformationWithoutAddress::class;
    }
  }

}
