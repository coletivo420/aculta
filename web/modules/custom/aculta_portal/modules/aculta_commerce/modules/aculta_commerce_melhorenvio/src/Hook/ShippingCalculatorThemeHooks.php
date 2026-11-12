<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Registro do template da calculadora de frete, de propriedade do módulo (não do tema).
 */
class ShippingCalculatorThemeHooks {

  public function __construct(
    #[Autowire(service: 'extension.list.module')]
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Tema de referência, que o template usa de forma neutra (sem componente de tema).
   */
  public const LIBRARY = 'aculta_commerce_melhorenvio/shipping-calculator';

  /**
   * Registra o template aculta_shipping_calculator.
   *
   * @return array<string, array<string, mixed>>
   *   Registro do template da calculadora de frete.
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
          'variation_id' => 0,
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
    return $this->moduleList->getPath('aculta_commerce_melhorenvio') . '/templates';
  }

}
