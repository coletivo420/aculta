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
   * Caminho do simulador (rota aculta_commerce_melhorenvio.simulate).
   */
  public const ENDPOINT = '/frete/simular';

  /**
   * Link "Não sei meu CEP" (busca de CEP dos Correios, confirmada pelo responsável em 2026-10-27).
   */
  public const HELP_URL = 'https://buscacepinter.correios.com.br/app/endereco/index.php';

  /**
   * Cria o hook.
   */
  public function __construct(
    private readonly ShippingSimulator $simulator,
    private readonly ShippingQuoteStore $store,
  ) {}

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
   * Carrinho completo: o componente calcula (ou mostra o último cálculo). O recálculo ao mudar a quantidade não fica
   * aqui: é feito por CartRefreshSubscriber, nos eventos do CartManager, valendo para todos os carrinhos.
   */
  #[Hook('form_views_form_commerce_cart_form_default_alter')]
  public function alterCartForm(array &$form): void {
    $form['aculta_shipping_calculator'] = $this->element($this->props('frete-carrinho'), 100);
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
    // Bloco do módulo (vale para qualquer tema). Só na página completa do produto, não na miniatura do catálogo.
    if ($display->getMode() !== 'default') {
      return;
    }
    $build['aculta_shipping_calculator'] = $this->element($this->props('frete-produto'), 100);
  }

  /**
   * Props do simulador. Se o visitante já calculou (produto ou carrinho completo), mostra esse resultado e o CEP; senão,
   * o estado de disponibilidade da configuração. O carrinho lateral só lê; ele não calcula.
   *
   * @return array{id: string, state: string, message: string, options: list<array<string, mixed>>, postal_code: string}
   */
  public function props(string $id): array {
    // Endpoint e link explícitos: o include() do Twig não aplica os padrões do componente (ficavam vazios no navegador).
    return [
      'id' => $id,
      'endpoint' => self::ENDPOINT,
      'help_url' => self::HELP_URL,
    ] + $this->summary();
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

  /**
   * Elemento de render do bloco do módulo. Variáveis do template vão como chaves com '#', como o Drupal exige.
   *
   * @param array<string, mixed> $props
   *   Props do simulador (id, state, message, options, postal_code, endpoint, help_url).
   */
  private function element(array $props, int $weight): array {
    $element = [
      '#theme' => 'aculta_shipping_calculator',
      '#attached' => ['library' => [ShippingCalculatorThemeHooks::LIBRARY]],
      '#weight' => $weight,
    ];
    foreach ($props as $name => $value) {
      $element['#' . $name] = $value;
    }
    return $element;
  }

}
