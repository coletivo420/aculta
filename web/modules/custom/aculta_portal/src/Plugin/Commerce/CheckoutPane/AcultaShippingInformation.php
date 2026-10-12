<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_shipping\Plugin\Commerce\CheckoutPane\ShippingInformation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Envio do pedido com o mesmo endereço único do pedido (ver BillingAddressPane).
 *
 * O Commerce mantém um formulário de endereço próprio no pane de envio. Aqui ele some: o perfil de envio é a cópia do
 * endereço de cobrança, que é atualizada a partir do perfil do cliente (Meus dados > Endereço). Assim não existe um
 * segundo endereço para preencher, e o cálculo de frete usa o mesmo endereço do Mercado Pago. O pane mantém a
 * escolha do método de envio e as remessas do Commerce. Ver docs/portal/CHECKOUT-BILLING.md.
 */
#[CommerceCheckoutPane(
  id: 'aculta_shipping_information',
  label: new TranslatableMarkup('Envio'),
  wrapper_element: 'fieldset',
)]
final class AcultaShippingInformation extends ShippingInformation {

  /** {@inheritdoc} */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    if (!isset($pane_form['shipping_profile']['#inline_form'])) {
      return $pane_form;
    }
    // Só o inline form fica (o Commerce lê a entidade dele); os campos de endereço saem da árvore do formulário.
    $pane_form['shipping_profile'] = [
      '#parents' => $pane_form['shipping_profile']['#parents'],
      '#inline_form' => $pane_form['shipping_profile']['#inline_form'],
    ];
    return $pane_form;
  }

  /**
   * Perfil de envio = endereço de cobrança do pedido (a cópia do endereço único).
   */
  protected function getShippingProfile() {
    $billing = $this->checkoutFlow->getPane('aculta_billing_address');
    $profile = $billing instanceof BillingAddressPane ? $billing->billingCopy() : NULL;
    return $profile ?? parent::getShippingProfile();
  }

}
