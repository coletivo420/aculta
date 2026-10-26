<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Hook;

use Drupal\commerce_payment\Form\PaymentGatewayForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Protects credentials in the Mercado Pago gateway form.
 */
final class PaymentGatewayFormHooks {

  /**
   * Masks stored tokens and disables unused credential fields.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof PaymentGatewayForm
      || $formObject->getEntity()->id() !== 'mercado_pago'
      || !isset($form['configuration']['form'])) {
      return;
    }

    $configurationForm = &$form['configuration']['form'];
    foreach ([
      ['credentials_test', 'access_token_test'],
      ['credentials_prod', 'access_token_prod'],
    ] as [$fieldset, $key]) {
      if (isset($configurationForm[$fieldset][$key])) {
        $configurationForm[$fieldset][$key]['#type'] = 'password';
        $configurationForm[$fieldset][$key]['#default_value'] = '';
        unset($configurationForm[$fieldset][$key]['#value']);
        $configurationForm[$fieldset][$key]['#attributes']['autocomplete'] = 'new-password';
      }
    }
    foreach ([
      ['credentials_stage', 'public_key_stage'],
      ['credentials_stage', 'access_token_stage'],
      [NULL, 'client_id'],
      [NULL, 'client_secret'],
    ] as [$fieldset, $key]) {
      $element = &$configurationForm;
      if ($fieldset !== NULL) {
        $element = &$configurationForm[$fieldset];
      }
      if (isset($element[$key])) {
        $element[$key]['#disabled'] = TRUE;
        $element[$key]['#default_value'] = '';
        unset($element[$key]['#value']);
      }
    }
  }

}
