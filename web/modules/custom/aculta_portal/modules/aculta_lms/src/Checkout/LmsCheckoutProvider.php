<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contexto LMS: compras de curso, pedidos do tipo configurado (padrão `curso`) e o histórico `curso`, no carrinho e no checkout comuns do Commerce.
 *
 * O LMS lê o mesmo valor de `CheckoutSettings` ao criar compras e reconhecer pedidos; o provider apenas o declara.
 */
final class LmsCheckoutProvider implements CheckoutContextProviderInterface {

  public function __construct(
    private readonly OrderTypeCheckoutFlows $flows,
    private readonly CheckoutSettings $settings,
  ) {}

  public function id(): string {
    return 'lms';
  }

  public function label(): TranslatableMarkup {
    return new TranslatableMarkup('LMS');
  }

  public function weight(): int {
    return 20;
  }

  public function configuration(): CheckoutContextConfiguration {
    $tipos = $this->settings->lmsOrderTypes();
    return new CheckoutContextConfiguration('lms', CheckoutMode::Common, $tipos, array_filter([$this->flows->flowId($this->settings->lmsOrderType())]));
  }

}
