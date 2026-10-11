<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Endereço de cobrança do pedido, com autocomplete por CEP.
 *
 * Substitui o pane billing_information, removido pelo Commerce Payment (hook de alteração de panes). O Mercado
 * Pago exige o perfil de cobrança com endereço no pedido (RedirectCheckoutForm). Usa o mesmo formulário inline de
 * perfil do Commerce, com escopo de cobrança e apenas o campo de endereço, sem abas. O autocomplete por CEP é o
 * mesmo de "Meus Dados": cep_autocomplete/viacep, que o Portal estende com aculta_portal/cep-address. O módulo
 * contrib só anexa essa biblioteca em formulários de perfil customer, então o pane a anexa explicitamente.
 * Ver docs/portal/CHECKOUT-BILLING.md.
 */
#[CommerceCheckoutPane(
  id: 'aculta_billing_address',
  label: new TranslatableMarkup('Endereço de cobrança'),
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
    if (!$profile) {
      $profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => 0,
      ]);
    }
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      // O perfil só entra na libreta de endereços quando o pedido é concluído.
      'copy_on_save' => FALSE,
    ], $profile);

    $pane_form['profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['profile']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['profile'] = $inline_form->buildInlineForm($pane_form['profile'], $form_state);
    // Número: o endereço de cobrança do Commerce não tem campo próprio. O campo entra dentro do bloco de endereço,
    // logo depois do Logradouro (address_line1), e o valor é gravado no logradouro. No resumo não há subcampos.
    $this->insertStreetNumber($pane_form['profile'], $this->savedStreetNumber($profile));
    $pane_form['#attached']['library'][] = self::CEP_LIBRARY;

    return $pane_form;
  }

  /** {@inheritdoc} */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['profile']['#inline_form'];
    $profile = $inline_form->getEntity();
    $number_element = $this->findElement($pane_form, 'street_number');
    $number = $number_element ? trim((string) $form_state->getValue($number_element['#parents'])) : '';
    if ($number !== '' && $profile->hasField('address') && !$profile->get('address')->isEmpty()) {
      $address = $profile->get('address')->first();
      $street = trim(preg_replace('/,\s*[^,]*$/', '', (string) $address->getAddressLine1()));
      // AddressItem não tem setAddressLine1(): grava-se o array de valores do endereço.
      $value = $address->getValue();
      $value['address_line1'] = $street . ', ' . $number;
      $address->setValue($value);
      $profile->save();
    }
    $this->order->setBillingProfile($profile);
  }

  /** Insere o campo Número como irmão de address_line1, em qualquer nível do formulário inline. */
  private function insertStreetNumber(array &$tree, string $default): bool {
    if (isset($tree['address_line1']) && is_array($tree['address_line1'])) {
      $tree['street_number'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Número'),
        '#required' => TRUE,
        '#maxlength' => 20,
        '#default_value' => $default,
        '#weight' => ($tree['address_line1']['#weight'] ?? 0) + 0.5,
      ];
      return TRUE;
    }
    foreach ($tree as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#') && $this->insertStreetNumber($child, $default)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** Busca o elemento do formulário pela chave, em qualquer nível. */
  private function findElement(array $tree, string $key): ?array {
    if (isset($tree[$key]) && is_array($tree[$key])) {
      return $tree[$key];
    }
    foreach ($tree as $child_key => $child) {
      if (is_array($child) && !str_starts_with((string) $child_key, '#')) {
        $found = $this->findElement($child, $key);
        if ($found !== NULL) {
          return $found;
        }
      }
    }
    return NULL;
  }

  /** Número já gravado no logradouro (último trecho após vírgula), para preencher o campo ao voltar à etapa. */
  private function savedStreetNumber($profile): string {
    if (!$profile || !$profile->hasField('address') || $profile->get('address')->isEmpty()) {
      return '';
    }
    $line = (string) $profile->get('address')->first()->getAddressLine1();
    return preg_match('/,\s*([^,]+)$/', $line, $m) ? trim($m[1]) : '';
  }

}
