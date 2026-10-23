<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Checkout;

use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contexto Doações: lê `aculta_donation.settings` e traduz `donation_route` para o vocabulário neutro do Portal.
 *
 * donate → dedicado, cart → comum, both → os dois. Valor ausente ou desconhecido equivale a `donate`, como na
 * decisão atual de rotas (ver CartCheckoutRoutesCharacterizationTest). A assinatura recorrente usa o tipo
 * `assinatura`, fixo em `NewOrder`.
 */
final class DonationCheckoutProvider implements CheckoutContextProviderInterface {

  public const SUBSCRIPTION_ORDER_TYPE = 'assinatura';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly OrderTypeCheckoutFlows $flows,
  ) {}

  public function id(): string {
    return 'donation';
  }

  public function label(): TranslatableMarkup {
    return new TranslatableMarkup('Doações');
  }

  public function weight(): int {
    return 30;
  }

  public function configuration(): CheckoutContextConfiguration {
    $config = $this->configFactory->get('aculta_donation.settings');
    $mode = match ($config->get('donation_route')) {
      DonationSettingsForm::ROTA_CART => CheckoutMode::Common,
      DonationSettingsForm::ROTA_AMBAS => CheckoutMode::Both,
      default => CheckoutMode::Dedicated,
    };
    $orderTypes = array_values(array_filter([
      $config->get('donate_order_type'),
      $config->get('cart_order_type'),
      self::SUBSCRIPTION_ORDER_TYPE,
    ], 'is_string'));
    $flows = array_values(array_filter([
      $config->get('donate_checkout_flow'),
      $config->get('cart_checkout_flow'),
      $this->flows->flowId(self::SUBSCRIPTION_ORDER_TYPE),
    ], 'is_string'));
    return new CheckoutContextConfiguration('donation', $mode, $orderTypes, $flows);
  }

}
