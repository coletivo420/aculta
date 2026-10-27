<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Hook;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Encaixe do simulador de frete na página do produto.
 *
 * O estado vem do serviço de simulação; o tema só recebe as props do componente shipping-calculator.
 */
class ShippingCalculatorHooks {

  /**
   * Bundle da loja que mostra o simulador na página do produto.
   */
  public const BUNDLE = 'produto_loja';

  /**
   * Tag de cache do objeto de configuração da origem. Sem ela, trocar a origem não atualiza a página já cacheada.
   */
  public const CONFIG_TAG = 'config:aculta_commerce_melhorenvio.settings';

  /**
   * Cria o hook.
   */
  public function __construct(
    private readonly ShippingSimulator $simulator,
  ) {}

  /**
   * Adiciona as props do simulador à página completa do produto. A miniatura do catálogo não tem simulador.
   */
  #[Hook('preprocess_commerce_product')]
  public function preprocessCommerceProduct(array &$variables): void {
    $product = $variables['elements']['#commerce_product'] ?? NULL;
    if ($product === NULL || $product->bundle() !== self::BUNDLE) {
      return;
    }
    if (($variables['elements']['#view_mode'] ?? 'default') === 'catalog') {
      return;
    }
    $variables['shipping_calculator'] = $this->props('frete-produto');
  }

  /**
   * Inclui a configuração da origem no cache do produto: mudar a origem invalida a página.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if ($entity->getEntityTypeId() !== 'commerce_product' || $entity->bundle() !== self::BUNDLE) {
      return;
    }
    $build['#cache']['tags'][] = self::CONFIG_TAG;
  }

  /**
   * Props do componente shipping-calculator para o estado atual, sem CEP digitado.
   *
   * @return array{id: string, state: string, message: string}
   */
  public function props(string $id): array {
    $reason = $this->simulator->availabilityReason();
    if ($reason === NULL) {
      return ['id' => $id, 'state' => 'idle', 'message' => ''];
    }
    return [
      'id' => $id,
      'state' => 'unavailable',
      'message' => ShippingSimulation::unavailable($reason)->message(),
    ];
  }

}
