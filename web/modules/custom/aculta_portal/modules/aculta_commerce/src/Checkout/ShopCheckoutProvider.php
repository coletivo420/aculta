<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contexto Loja: pedidos do tipo `default`, no carrinho e no checkout comuns do Commerce.
 */
final class ShopCheckoutProvider implements CheckoutContextProviderInterface {

  public const ORDER_TYPE = 'default';

  public function __construct(private readonly OrderTypeCheckoutFlows $flows) {}

  public function id(): string {
    return 'shop';
  }

  public function label(): TranslatableMarkup {
    return new TranslatableMarkup('Loja');
  }

  public function weight(): int {
    return 10;
  }

  public function configuration(): CheckoutContextConfiguration {
    return new CheckoutContextConfiguration('shop', CheckoutMode::Common, [self::ORDER_TYPE], array_filter([$this->flows->flowId(self::ORDER_TYPE)]));
  }

}
