<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\aculta_commerce\Shipping\PackerManagerInterface;
use Drupal\aculta_commerce\Shipping\ShipmentManagerInterface;
use Drupal\aculta_commerce\Shipping\ShippingOrderManagerInterface;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the ACULTA shipping information checkout pane.
 *
 * Replaces the contributed ShippingInformation pane used by the Portal. The
 * order keeps a single address: the shipping profile is a copy of the billing
 * address maintained by the Portal's BillingAddressPane. Rates are always
 * recalculated on the server, never read from the browser, and the selected
 * rate is revalidated before it is applied.
 */
#[CommerceCheckoutPane(
  id: 'aculta_shipping_information',
  label: new TranslatableMarkup('Envio'),
  wrapper_element: 'fieldset',
)]
final class AcultaShippingInformation extends CheckoutPaneBase {

  /**
   * The shipping order manager.
   */
  protected ShippingOrderManagerInterface $shippingOrderManager;

  /**
   * The shipment manager.
   */
  protected ShipmentManagerInterface $shipmentManager;

  /**
   * The packer manager.
   */
  protected PackerManagerInterface $packerManager;

  /**
   * The currency formatter.
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
    );
    $instance->shippingOrderManager = $container->get('aculta_commerce.shipping_order_manager');
    $instance->shipmentManager = $container->get('aculta_commerce.shipment_manager');
    $instance->packerManager = $container->get('aculta_commerce.packer_manager');
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'require_shipping_profile' => TRUE,
      'auto_recalculate' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['require_shipping_profile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide shipping costs until an address is entered'),
      '#default_value' => $this->configuration['require_shipping_profile'],
    ];
    $form['auto_recalculate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto recalculate shipping costs when the shipping address changes'),
      '#default_value' => $this->configuration['auto_recalculate'],
      '#states' => [
        'visible' => [
          ':input[name="configuration[panes][aculta_shipping_information][configuration][require_shipping_profile]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['require_shipping_profile'] = !empty($values['require_shipping_profile']);
      $this->configuration['auto_recalculate'] = !empty($values['auto_recalculate']) && $this->configuration['require_shipping_profile'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    return $this->shippingOrderManager->isShippable($this->order);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary(): array {
    if (!$this->isVisible()) {
      return [];
    }
    $summary = [];
    foreach ($this->order->get('shipments')->referencedEntities() as $shipment) {
      if ($amount = $shipment->getAmount()) {
        $summary[] = [
          '#markup' => $this->t('@service: @amount', [
            '@service' => $shipment->getShippingServiceLabel() ?: $shipment->getTitle(),
            '@amount' => $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode()),
          ]),
        ];
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    if (!$this->isVisible()) {
      return $pane_form;
    }
    $profile = $this->getShippingProfile();
    if (!$profile || !$this->canCalculateRates($profile)) {
      $pane_form['message'] = [
        '#markup' => '<p>' . $this->t('Informe o endereço de entrega para calcular o frete.') . '</p>',
      ];
      return $pane_form;
    }

    $shipments = $this->order->get('shipments')->referencedEntities();
    if (!$shipments) {
      [$shipments] = $this->packerManager->packToShipments($this->order, $profile, []);
    }
    if (!$shipments) {
      $pane_form['message'] = [
        '#markup' => '<p>' . $this->t('Não foi possível calcular o envio para os itens deste pedido.') . '</p>',
      ];
      return $pane_form;
    }

    $address_hash = $this->addressHash($profile);
    $pane_form['shipments'] = ['#type' => 'container'];
    foreach ($shipments as $index => $shipment) {
      $shipment->setShippingProfile($profile);
      // Invalidate a previously selected rate when the address changed.
      if ($shipment->getAmount() && $shipment->getData('rate_address_hash') !== $address_hash) {
        $shipment->clearRate();
      }
      $rates = $this->shipmentManager->calculateRates($shipment);
      if (!$rates) {
        $pane_form['shipments'][$index]['empty'] = [
          '#markup' => '<p class="aculta-shipping-rates-empty">' . $this->t('Não há frete disponível para este endereço.') . '</p>',
        ];
        continue;
      }
      $options = [];
      foreach ($rates as $rate_id => $rate) {
        $options[$rate_id] = $this->t('@service — @amount', [
          '@service' => $rate->getService()->getLabel(),
          '@amount' => $this->currencyFormatter->format($rate->getAmount()->getNumber(), $rate->getAmount()->getCurrencyCode()),
        ]);
      }
      $default_rate = $this->shipmentManager->selectDefaultRate($shipment, $rates);
      $default_id = $rates ? $default_rate->getId() : NULL;
      if ($shipment->getShippingMethodId() && $shipment->getShippingService()) {
        $current_id = $shipment->getShippingMethodId() . '--' . $shipment->getShippingService();
        if (isset($rates[$current_id])) {
          $default_id = $current_id;
        }
      }
      $pane_form['shipments'][$index]['shipping_method'] = [
        '#type' => 'radios',
        '#parents' => array_merge($pane_form['#parents'], ['shipments', $index, 'shipping_method']),
        '#title' => $shipment->getTitle(),
        '#options' => $options,
        '#default_value' => $default_id,
        '#required' => TRUE,
      ];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    if (!$this->isVisible()) {
      return;
    }
    $profile = $this->getShippingProfile();
    if (!$profile || !$this->canCalculateRates($profile)) {
      $form_state->setError($pane_form, $this->t('Informe um endereço de entrega válido para calcular o frete.'));
      return;
    }
    $shipments = $this->order->get('shipments')->referencedEntities();
    if (!$shipments) {
      [$shipments] = $this->packerManager->packToShipments($this->order, $profile, []);
    }
    if (!$shipments) {
      $form_state->setError($pane_form, $this->t('Não foi possível calcular o envio para os itens deste pedido.'));
      return;
    }
    foreach ($shipments as $index => $shipment) {
      $shipment->setShippingProfile($profile);
      $rates = $this->shipmentManager->calculateRates($shipment);
      if (!$rates) {
        $form_state->setError($pane_form, $this->t('Não há frete disponível para este endereço.'));
        continue;
      }
      $selected = $form_state->getValue(array_merge($pane_form['#parents'], ['shipments', $index, 'shipping_method']));
      if (!$selected || !isset($rates[$selected])) {
        $form_state->setError($pane_form, $this->t('Selecione uma forma de envio válida.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    if (!$this->isVisible()) {
      return;
    }
    $profile = $this->getShippingProfile();
    if (!$profile || !$this->canCalculateRates($profile)) {
      return;
    }
    $shipments = $this->order->get('shipments')->referencedEntities();
    if (!$shipments) {
      [$shipments] = $this->packerManager->packToShipments($this->order, $profile, []);
    }
    $address_hash = $this->addressHash($profile);
    foreach ($shipments as $index => $shipment) {
      $shipment->setShippingProfile($profile);
      $rates = $this->shipmentManager->calculateRates($shipment);
      $selected = $form_state->getValue(array_merge($pane_form['#parents'], ['shipments', $index, 'shipping_method']));
      if ($selected && isset($rates[$selected])) {
        // Only a rate produced by a trusted provider in this request is
        // applied; the amount never comes from the submitted form.
        $this->shipmentManager->applyRate($shipment, $rates[$selected]);
        $shipment->setData('rate_address_hash', $address_hash);
      }
      $shipment->save();
    }
    $this->order->set('shipments', $shipments);
  }

  /**
   * Gets the shipping profile.
   *
   * The Portal keeps a single address. When the billing pane is present, its
   * address copy is used as the shipping profile; otherwise the shipment
   * profile or a new profile is used.
   */
  protected function getShippingProfile(): ?ProfileInterface {
    $billing_pane = $this->checkoutFlow->getPane('aculta_billing_address');
    if ($billing_pane && method_exists($billing_pane, 'billingCopy')) {
      $profile = $billing_pane->billingCopy();
      if ($profile instanceof ProfileInterface) {
        return $profile;
      }
    }
    foreach ($this->order->get('shipments')->referencedEntities() as $shipment) {
      $profile = $shipment->getShippingProfile();
      if ($profile) {
        return $profile;
      }
    }
    $billing_profile = $this->order->getBillingProfile();
    if ($billing_profile instanceof ProfileInterface) {
      return $billing_profile;
    }
    return $this->shippingOrderManager->createProfile($this->order);
  }

  /**
   * Gets whether rates can be calculated for the given profile.
   */
  protected function canCalculateRates(ProfileInterface $profile): bool {
    if (empty($this->configuration['require_shipping_profile'])) {
      return TRUE;
    }
    $item = NULL;
    foreach (['address', 'field_address'] as $field_name) {
      if ($profile->hasField($field_name) && !$profile->get($field_name)->isEmpty()) {
        $item = $profile->get($field_name)->first();
        break;
      }
    }
    if (!$item) {
      return FALSE;
    }
    return count($item->validate()) === 0;
  }

  /**
   * Builds a non-reversible fingerprint of the shipping address.
   */
  protected function addressHash(ProfileInterface $profile): string {
    $value = [];
    foreach (['address', 'field_address'] as $field_name) {
      if ($profile->hasField($field_name) && !$profile->get($field_name)->isEmpty()) {
        $value = $profile->get($field_name)->first()->getValue();
        break;
      }
    }
    return hash('sha256', json_encode($value));
  }

}
