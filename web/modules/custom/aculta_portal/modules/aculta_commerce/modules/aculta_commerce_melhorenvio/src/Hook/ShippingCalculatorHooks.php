<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Hook;

use Drupal\aculta_commerce_melhorenvio\Quote\CartQuoteLines;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Adds the shipping calculator to product and cart displays.
 *
 * The simulator determines state; the theme receives component props.
 */
class ShippingCalculatorHooks {

  /**
   * Product bundle that displays the shipping calculator.
   */
  public const BUNDLE = 'produto_loja';

  /**
   * Cache tag for shipping origin configuration.
   */
  public const CONFIG_TAG = 'config:aculta_commerce_melhorenvio.settings';

  /**
   * Path for the shipping simulator route.
   */
  public const ENDPOINT = '/frete/simular';

  /**
   * Correios postal code lookup help URL.
   */
  public const HELP_URL = 'https://buscacepinter.correios.com.br/app/endereco/index.php';

  /**
   * Cria o hook.
   */
  public function __construct(
    private readonly ShippingSimulator $simulator,
    private readonly ShippingQuoteStore $store,
    private readonly CartQuoteLines $cartLines,
  ) {}

  /**
   * Adds shipping details to page variables for the cart drawer.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_shipping_summary'] = $this->summary();
  }

  /**
   * Adds shipping details to the AJAX cart panel variables.
   */
  #[Hook('preprocess_aculta_portal_cart_panel')]
  public function preprocessCartPanel(array &$variables): void {
    $variables['shipping'] = $this->summary();
  }

  /**
   * Adds the calculator to the full cart form.
   *
   * CartRefreshSubscriber recalculates after quantity changes.
   */
  #[Hook('form_views_form_commerce_cart_form_default_alter')]
  public function alterCartForm(array &$form): void {
    $form['aculta_shipping_calculator'] = $this->element($this->props('frete-carrinho'), 100);
  }

  /**
   * Adds the shipping origin configuration to the product cache tags.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if ($entity->getEntityTypeId() !== 'commerce_product' || $entity->bundle() !== self::BUNDLE) {
      return;
    }
    $build['#cache']['tags'][] = self::CONFIG_TAG;
    // The block works with any theme and only appears on full product pages.
    if ($display->getMode() !== 'default') {
      return;
    }
    $variation = $entity->getDefaultVariation();
    $props = $this->props('frete-produto', $variation) + ['variation_id' => $variation ? (int) $variation->id() : 0];
    $build['aculta_shipping_calculator'] = $this->element($props, 100);
  }

  /**
   * Builds component props from the saved quote or current availability.
   *
   * The drawer reads the quote but does not calculate it.
   *
   * @param string $id
   *   Component instance ID.
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation
   *   Optional store variation used to determine quote availability.
   *
   * @return array{
   *   id: string,
   *   state: string,
   *   message: string,
   *   options: list<array<string, mixed>>,
   *   postal_code: string
   *   }
   *   Props for the reusable shipping calculator component.
   */
  public function props(string $id, ?ProductVariationInterface $variation = NULL): array {
    // Twig include() does not apply the endpoint or help link defaults.
    $base = ['id' => $id, 'endpoint' => self::ENDPOINT, 'help_url' => self::HELP_URL];
    // Without package dimensions, the product cannot reuse another quote.
    if ($variation !== NULL) {
      $reason = $this->simulator->availabilityReason($variation);
      if ($reason !== NULL) {
        return $base + [
          'state' => 'unavailable',
          'message' => ShippingSimulation::unavailable($reason)->message(),
          'options' => [],
          'postal_code' => $this->store->loadLastPostalCode() ?? '',
        ];
      }
    }
    return $base + $this->summary();
  }

  /**
   * Returns the saved quote or current availability for the cart drawer.
   *
   * @return array{
   *   state: string,
   *   message: string,
   *   options: list<array<string, mixed>>,
   *   postal_code: string
   *   }
   *   Current quote or idle/unavailable state, including the saved postal code.
   */
  public function summary(): array {
    // Carrinho atual com itens da loja e CEP conhecido: cota agora (cache do simulador). Vale sobre qualquer resultado
    // guardado, que pode ter sido calculado antes do item entrar no carrinho.
    $postal = $this->store->loadLastPostalCode();
    $lines = $this->cartLines->lines();
    if ($postal !== NULL && $lines !== []) {
      $result = $this->simulator->simulateForCartLines($postal, $lines);
      $this->store->save($postal, $result);
      return [
        'state' => $result->state,
        'message' => $result->message(),
        'options' => $result->toArray()['options'],
        'postal_code' => $postal,
      ];
    }
    // Sem itens da loja no carrinho, não há frete a mostrar.
    if ($lines === []) {
      return ['state' => 'idle', 'message' => '', 'options' => [], 'postal_code' => $postal ?? ''];
    }
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
      'postal_code' => $postal ?? '',
    ];
  }

  /**
   * Builds the module's calculator render element.
   *
   * @param array<string, mixed> $props
   *   Calculator component props.
   * @param int $weight
   *   Render array weight.
   */
  private function element(array $props, int $weight): array {
    $element = [
      '#theme' => 'aculta_shipping_calculator',
      '#attached' => ['library' => [ShippingCalculatorThemeHooks::LIBRARY]],
      '#weight' => $weight,
      // Contains per-account/session CEP data; prevent render-cache leakage.
      '#cache' => [
        'contexts' => ['user', 'session'],
        'max-age' => 0,
      ],
    ];
    foreach ($props as $name => $value) {
      $element['#' . $name] = $value;
    }
    return $element;
  }

}
