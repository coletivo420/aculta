<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod;

use Drupal\aculta_commerce\Attribute\AcultaShippingMethod;
use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\aculta_commerce\Shipping\ShippingService;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a flat rate shipping method.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). A method
 * without a configured amount fails closed instead of returning a zero rate.
 */
#[AcultaShippingMethod(
  id: 'aculta_flat_rate',
  label: new TranslatableMarkup('Tarifa fixa'),
  services: ['default' => 'Tarifa fixa'],
  workflow: 'aculta_shipment_default',
)]
class FlatRate extends ShippingMethodBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rate_label' => '',
      'rate_description' => '',
      'rate_amount' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment): array {
    $parent = $this->getParentEntity();
    if (!$parent) {
      throw new \LogicException('The flat rate shipping method has no parent entity.');
    }
    $amount = $this->configuration['rate_amount'] ?? NULL;
    if (!is_array($amount) || !isset($amount['number'], $amount['currency_code'])) {
      throw new \InvalidArgumentException('The flat rate shipping method has no configured amount.');
    }

    return [
      new ShippingRate([
        'shipping_method_id' => (string) $parent->id(),
        'service' => new ShippingService('default', (string) ($this->configuration['rate_label'] ?: $this->getLabel())),
        'amount' => Price::fromArray($amount),
        'description' => (string) $this->configuration['rate_description'],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $amount = $this->configuration['rate_amount'];
    if (isset($amount) && !isset($amount['number'], $amount['currency_code'])) {
      $amount = NULL;
    }
    $form['rate_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rate label'),
      '#description' => $this->t('Shown to customers when selecting the rate.'),
      '#default_value' => $this->configuration['rate_label'],
      '#required' => TRUE,
    ];
    $form['rate_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rate description'),
      '#default_value' => $this->configuration['rate_description'],
    ];
    $form['rate_amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Rate amount'),
      '#default_value' => $amount,
      '#required' => TRUE,
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
      $this->configuration['rate_label'] = $values['rate_label'];
      $this->configuration['rate_description'] = $values['rate_description'];
      $this->configuration['rate_amount'] = $values['rate_amount'];
    }
  }

}
