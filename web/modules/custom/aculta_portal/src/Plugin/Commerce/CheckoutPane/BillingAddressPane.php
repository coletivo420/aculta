<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Endereço do pedido (um único endereço do site, usado também no Mercado Pago), com autocomplete por CEP.
 *
 * Fonte única: o perfil customer do cliente (o de Meus Dados > Endereço). O Commerce não aceita o perfil do cliente
 * como perfil de cobrança do pedido (Order::preSave passa o perfil de cobrança para o dono 0), então o pedido guarda
 * uma cópia. A cópia é atualizada a partir do perfil do cliente em todo resumo de rascunho, e a edição no checkout
 * grava no perfil do cliente e na cópia. O Mercado Pago lê a cópia (RedirectCheckoutForm). Ver docs/portal/CHECKOUT-BILLING.md.
 *
 * Número: o perfil guarda o número no logradouro ("Avenida Paulista, 1000"). O formulário mostra a rua e o campo
 * Número recebe o número. Ao gravar, rua e número são juntados de novo. Ver StreetNumberField.
 */
#[CommerceCheckoutPane(
  id: 'aculta_billing_address',
  label: new TranslatableMarkup('Endereço'),
  default_step: 'order_information',
  wrapper_element: 'fieldset',
)]
final class BillingAddressPane extends CheckoutPaneBase implements CheckoutPaneInterface {

  private InlineFormManager $inlineFormManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    return $instance;
  }

  /** {@inheritdoc} */
  public function buildPaneSummary() {
    if (!$profile = $this->billingCopy()) {
      return [];
    }
    return $this->entityTypeManager->getViewBuilder('profile')->view($profile, 'default');
  }

  /** {@inheritdoc} */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $profile = $this->customerProfile() ?? $this->entityTypeManager->getStorage('profile')->create([
      'type' => 'authenticated',
      'uid' => (int) $this->order->getCustomerId(),
      'status' => TRUE,
      'is_default' => TRUE,
    ]);
    // O formulário mostra só a rua. A cópia é usada só no formulário; o perfil do cliente não muda até o envio.
    $parts = $this->savedParts($profile);
    $editable = $profile;
    if ($parts['number'] !== '' || $parts['sn']) {
      $editable = clone $profile;
      $address = $editable->get('field_address')->first();
      $value = $address->getValue();
      $value['address_line1'] = $parts['street'];
      $address->setValue($value);
    }
    // O endereço fica sempre em edição no checkout: o resumo do Commerce tinha um "Editar" que não abria o formulário
    // em todos os casos. O formulário lê o perfil de Meus Dados e salva nele; número, S/N e bairro são validados aqui.
    $form_state->set(array_merge($pane_form['#parents'], ['profile', 'render']), FALSE);
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      // O perfil só entra na libreta de endereços quando o pedido é concluído.
      'copy_on_save' => FALSE,
    ], $editable);

    $pane_form['profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['profile']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['profile'] = $inline_form->buildInlineForm($pane_form['profile'], $form_state);
    // Mesmo formulário de Meus Dados (AccountAddressForm): país fixo, rótulos, Número e S/N como subcampo do endereço.
    AccountAddressForm::apply($pane_form['profile'], $parts['number'], $parts['sn']);
    // Anexado ao formulário da página: o Commerce não leva a biblioteca do pane (#attached) até a página.
    $complete_form['#attached']['library'][] = AccountAddressForm::LIBRARY;

    return $pane_form;
  }

  /**
   * Número obrigatório também quando o campo não veio no envio (resumo do endereço): a validação do campo só roda
   * quando o formulário está em edição. Sem número nem S/N, o pedido não avança e a etapa reabre em edição.
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if (!isset($pane_form['profile']['#inline_form'])) {
      return;
    }
    // Campo de número enviado (modo edição): a validação do próprio campo já rodou.
    $submitted = $form_state->getValue(array_merge($pane_form['#parents'], ['profile', 'field_address', 0, 'address']));
    if (is_array($submitted) && array_key_exists('street_number', $submitted)) {
      return;
    }
    $profile = $pane_form['profile']['#inline_form']->getEntity();
    $parts = $this->savedParts($profile);
    if ($parts['number'] === '' && !$parts['sn']) {
      $form_state->setError($pane_form['profile'], $this->t('Informe o número ou marque S/N.'));
    }
    if (!$this->hasNeighborhood($profile)) {
      $form_state->setError($pane_form['profile'], $this->t('O campo Bairro é obrigatório.'));
    }
  }

  /** {@inheritdoc} */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['profile']['#inline_form'];
    $profile = $inline_form->getEntity();
    [$number, $sn] = StreetNumberField::values($pane_form, $form_state);
    if ($profile->hasField('field_address') && !$profile->get('field_address')->isEmpty()) {
      $address = $profile->get('field_address')->first();
      $value = $address->getValue();
      $value['address_line1'] = StreetNumberField::join((string) $address->getAddressLine1(), $number, $sn);
      $address->setValue($value);
    }
    $profile->set('uid', (int) $this->order->getCustomerId());
    $profile->set('is_default', TRUE);
    $profile->set('status', TRUE);
    $profile->save();
    $this->order->setBillingProfile($this->syncBillingCopy($profile));
  }

  /** Perfil do cliente do pedido (Meus Dados > Endereço, tipo authenticated), ou NULL. */
  private function customerProfile(): ?ProfileInterface {
    if ((int) $this->order->getCustomerId() <= 0) {
      return NULL;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($this->order->getCustomerId());
    return $account ? $this->entityTypeManager->getStorage('profile')->loadByUser($account, 'authenticated') : NULL;
  }

  /**
   * Endereço de cobrança do pedido. Rascunho: cópia atualizada a partir do perfil do cliente. Pedido concluído: o
   * perfil gravado no fechamento. Público para o pane de envio usar o mesmo endereço (ver AcultaShippingInformation).
   */
  public function billingCopy(): ?ProfileInterface {
    if ($this->order->getState()->getId() !== 'draft') {
      return $this->order->getBillingProfile();
    }
    $customer = $this->customerProfile();
    return $customer ? $this->syncBillingCopy($customer) : $this->order->getBillingProfile();
  }

  /** Copia o endereço do perfil do cliente para o perfil de cobrança do pedido e salva o pedido se houve troca. */
  private function syncBillingCopy(ProfileInterface $customer): ProfileInterface {
    $storage = $this->entityTypeManager->getStorage('profile');
    $billing = $this->order->getBillingProfile() ?? $storage->create(['type' => 'authenticated', 'uid' => 0]);
    $address = $customer->get('field_address')->getValue();
    if ($billing->isNew() || $billing->get('field_address')->getValue() !== $address) {
      $billing->set('field_address', $address);
      $billing->save();
    }
    if ($this->order->getBillingProfile()?->id() !== $billing->id()) {
      $this->order->setBillingProfile($billing);
      $this->order->save();
    }
    return $billing;
  }

  /** Bairro (dependent_locality) obrigatório: o endereço salvo precisa ter bairro preenchido. */
  private function hasNeighborhood($profile): bool {
    if (!$profile || !$profile->hasField('field_address') || $profile->get('field_address')->isEmpty()) {
      return FALSE;
    }
    return trim((string) $profile->get('field_address')->first()->getDependentLocality()) !== '';
  }

  /** Rua, número e S/N salvos no logradouro (para preencher o formulário ao voltar à etapa). */
  private function savedParts($profile): array {
    if (!$profile || !$profile->hasField('field_address') || $profile->get('field_address')->isEmpty()) {
      return ['street' => '', 'number' => '', 'sn' => FALSE];
    }
    return StreetNumberField::split((string) $profile->get('field_address')->first()->getAddressLine1());
  }

}
