<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Checkout;

use Drupal\aculta_portal\Checkout\CheckoutContextConfiguration;
use Drupal\aculta_portal\Checkout\CheckoutContextProviderInterface;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contexto LMS: compras de curso, pedidos do tipo `curso`, no carrinho e no checkout comuns do Commerce.
 *
 * O tipo `curso` é fixo no LMS (`CourseOrderEnrollmentManager`, `CourseEnrollmentPurchaseForm`); o provider apenas o declara.
 */
final class LmsCheckoutProvider implements CheckoutContextProviderInterface {

  public const ORDER_TYPE = 'curso';

  public function __construct(private readonly OrderTypeCheckoutFlows $flows) {}

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
    return new CheckoutContextConfiguration('lms', CheckoutMode::Common, [self::ORDER_TYPE], array_filter([$this->flows->flowId(self::ORDER_TYPE)]));
  }

}
