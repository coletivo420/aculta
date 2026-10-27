<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\commerce_price\Price;
use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\aculta_commerce\Shipping\ShippingService;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Converte opções cotadas em tarifas do motor de frete ACULTA.
 */
final class QuoteRateFactory {

  /**
   * Tarifas dos serviços permitidos, em reais.
   *
   * @param list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption> $options
   *   Opções cotadas.
   * @param string $shippingMethodId
   *   ID da entidade de método de envio.
   * @param list<string> $allowedServices
   *   IDs de serviço habilitados no método. Vazio permite todos.
   *
   * @return list<\Drupal\aculta_commerce\Shipping\ShippingRate>
   *   Tarifas.
   */
  public function rates(array $options, string $shippingMethodId, array $allowedServices): array {
    $rates = [];
    foreach ($options as $option) {
      if ($allowedServices !== [] && !in_array($option->serviceId, $allowedServices, TRUE)) {
        continue;
      }
      $rates[] = new ShippingRate([
        'shipping_method_id' => $shippingMethodId,
        'service' => new ShippingService($option->serviceId, $option->label()),
        'amount' => new Price($option->amount, 'BRL'),
        'description' => $option->deliveryDays !== NULL
          ? new TranslatableMarkup('Entrega em até @days dias úteis', ['@days' => $option->deliveryDays])
          : '',
        'data' => ['melhorenvio_service_id' => $option->serviceId],
      ]);
    }
    return $rates;
  }

}
