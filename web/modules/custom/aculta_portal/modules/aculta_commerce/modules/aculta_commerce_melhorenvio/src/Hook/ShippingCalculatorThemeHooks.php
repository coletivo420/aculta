<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registro do template da calculadora de frete, de propriedade do módulo (não do tema).
 */
class ShippingCalculatorThemeHooks {

  /**
   * Tema de referência, que o template usa de forma neutra (sem componente de tema).
   */
  public const LIBRARY = 'aculta_commerce_melhorenvio/shipping-calculator';

  /**
   * Registra o template aculta_shipping_calculator.
   *
   * @return array<string, array<string, mixed>>
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_shipping_calculator' => [
        'variables' => [
          'id' => 'aculta-frete',
          'state' => 'idle',
          'message' => '',
          'options' => [],
          'postal_code' => '',
          'endpoint' => '/frete/simular',
          'help_url' => 'https://buscacepinter.correios.com.br/app/endereco/index.php',
        ],
        'template' => 'aculta-shipping-calculator',
        'path' => $this->templatesPath(),
      ],
    ];
  }

  /**
   * Pasta de templates do módulo, pelo caminho real do módulo instalado.
   */
  private function templatesPath(): string {
    return \Drupal::service('extension.list.module')->getPath('aculta_commerce_melhorenvio') . '/templates';
  }

}
