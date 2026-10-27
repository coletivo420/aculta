<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Hook;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
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
    private readonly ShippingQuoteStore $store,
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
   * Props do simulador da gaveta do carrinho. A gaveta só o mostra com itens (ver cart-offcanvas).
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_shipping_summary'] = $this->summary();
  }

  /**
   * Resumo do frete no carrinho AJAX ("Adicionar ao carrinho" atualiza a gaveta sem recarregar a página).
   */
  #[Hook('preprocess_aculta_portal_cart_panel')]
  public function preprocessCartPanel(array &$variables): void {
    $variables['shipping'] = $this->summary();
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
   * Props do simulador. Se o visitante já calculou (produto ou carrinho completo), mostra esse resultado e o CEP; senão,
   * o estado de disponibilidade da configuração. O carrinho lateral só lê; ele não calcula.
   *
   * @return array{id: string, state: string, message: string, options: list<array<string, mixed>>, postal_code: string}
   */
  public function props(string $id): array {
    return ['id' => $id] + $this->summary();
  }

  /**
   * Estado do último cálculo (ou da disponibilidade, sem cálculo), sem id. Usado pelo resumo do carrinho lateral.
   *
   * @return array{state: string, message: string, options: list<array<string, mixed>>, postal_code: string}
   */
  public function summary(): array {
    $stored = $this->store->load();
    if ($stored !== NULL) {
      $result = $stored['result'];
      return [
        'state' => (string) $result['state'],
        'message' => (string) $result['message'],
        'options' => (array) $result['options'],
        'postal_code' => (string) $stored['postal_code'],
      ];
    }
    $reason = $this->simulator->availabilityReason();
    return [
      'state' => $reason === NULL ? 'idle' : 'unavailable',
      'message' => $reason === NULL ? '' : ShippingSimulation::unavailable($reason)->message(),
      'options' => [],
      'postal_code' => '',
    ];
  }

}
