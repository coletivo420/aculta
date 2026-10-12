<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Endereço do pedido (um único endereço do site, usado também no Mercado Pago), com autocomplete por CEP.
 *
 * Substitui o pane billing_information, removido pelo Commerce Payment (hook de alteração de panes). O Mercado
 * Pago exige o perfil de cobrança com endereço no pedido (RedirectCheckoutForm). Usa o mesmo formulário inline de
 * perfil do Commerce, com escopo de cobrança e apenas o endereço, sem abas. O autocomplete por CEP é o mesmo de
 * "Meus Dados": cep_autocomplete/viacep, que o Portal estende com aculta_portal/cep-address.
 *
 * Número: o perfil guarda o número no logradouro ("Avenida Paulista, 1000"). Ao montar o formulário, o logradouro
 * mostra só a rua ("Avenida Paulista") e o campo Número recebe o número. Ao gravar, rua e número são juntados de
 * novo. O script de CEP move o campo Número para logo depois do Logradouro. Ver docs/portal/CHECKOUT-BILLING.md.
 */
#[CommerceCheckoutPane(
  id: 'aculta_billing_address',
  label: new TranslatableMarkup('Endereço'),
  default_step: 'order_information',
  wrapper_element: 'fieldset',
)]
final class BillingAddressPane extends CheckoutPaneBase implements CheckoutPaneInterface {

  /** Biblioteca do CEP compartilhada com "Meus Dados" (estendida pelo Portal). */
  private const CEP_LIBRARY = 'cep_autocomplete/viacep';

  private InlineFormManager $inlineFormManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    return $instance;
  }

  /** {@inheritdoc} */
  public function buildPaneSummary() {
    if (!$profile = $this->order->getBillingProfile()) {
      return [];
    }
    return $this->entityTypeManager->getViewBuilder('profile')->view($profile, 'default');
  }

  /** {@inheritdoc} */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $profile = $this->order->getBillingProfile();
    if (!$profile && (int) $this->order->getCustomerId() > 0) {
      // Um único endereço: o pedido usa o endereço do cliente (o mesmo de Meus dados > Endereço), não um novo.
      $account = $this->entityTypeManager->getStorage('user')->load($this->order->getCustomerId());
      $profile = $account ? $this->entityTypeManager->getStorage('profile')->loadByUser($account, 'customer') : NULL;
    }
    if (!$profile) {
      $profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => (int) $this->order->getCustomerId(),
      ]);
    }
    // O formulário mostra só a rua. A cópia é usada só no formulário; o perfil original (resumo) não muda.
    $parts = $this->savedParts($profile);
    $editable = $profile;
    if ($parts['number'] !== '' || $parts['sn']) {
      $editable = clone $profile;
      $address = $editable->get('address')->first();
      $value = $address->getValue();
      $value['address_line1'] = $parts['street'];
      $address->setValue($value);
    }
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      // O perfil só entra na libreta de endereços quando o pedido é concluído.
      'copy_on_save' => FALSE,
    ], $editable);

    $pane_form['profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['profile']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['profile'] = $inline_form->buildInlineForm($pane_form['profile'], $form_state);
    // O Número é um subcampo do elemento de endereço (ver StreetNumberField), e não um campo solto do pane.
    StreetNumberField::attachTo($pane_form['profile'], $parts['number'], $parts['sn']);
    $pane_form['#attached']['library'][] = self::CEP_LIBRARY;

    return $pane_form;
  }

  /** {@inheritdoc} */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['profile']['#inline_form'];
    $profile = $inline_form->getEntity();
    [$number, $sn] = StreetNumberField::values($pane_form, $form_state);
    if ($profile->hasField('address') && !$profile->get('address')->isEmpty()) {
      $address = $profile->get('address')->first();
      $value = $address->getValue();
      $value['address_line1'] = StreetNumberField::join((string) $address->getAddressLine1(), $number, $sn);
      $address->setValue($value);
      $profile->save();
    }
    $this->order->setBillingProfile($profile);
  }

  /** Rua, número e S/N salvos no logradouro (para preencher o formulário ao voltar à etapa). */
  private function savedParts($profile): array {
    if (!$profile || !$profile->hasField('address') || $profile->get('address')->isEmpty()) {
      return ['street' => '', 'number' => '', 'sn' => FALSE];
    }
    return StreetNumberField::split((string) $profile->get('address')->first()->getAddressLine1());
  }

}
