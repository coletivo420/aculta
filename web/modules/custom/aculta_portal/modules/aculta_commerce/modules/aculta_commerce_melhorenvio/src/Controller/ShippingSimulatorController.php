<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Controller;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\CartQuoteLines;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Endpoint JSON do simulador de frete, chamado pelo componente shipping-calculator.
 *
 * Responde sempre com um dos estados do simulador. Limite de 30 consultas por minuto por IP, como as consultas de CEP.
 */
final class ShippingSimulatorController {

  private const FLOOD_NAME = 'aculta_commerce_melhorenvio.simulate';

  private const FLOOD_LIMIT = 30;

  private const FLOOD_WINDOW = 60;

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly ShippingSimulator $simulator,
    private readonly FloodInterface $flood,
    private readonly ShippingQuoteStore $store,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CartQuoteLines $cartLines,
  ) {}

  /**
   * Simulação para o CEP da consulta (parâmetro `cep`).
   */
  public function simulate(Request $request): JsonResponse {
    $ip = (string) $request->getClientIp();
    if (!$this->flood->isAllowed(self::FLOOD_NAME, self::FLOOD_LIMIT, self::FLOOD_WINDOW, $ip)) {
      return $this->json(ShippingSimulation::unavailable(ShippingSimulation::REASON_RATE_LIMITED)->toArray(), 429);
    }
    $this->flood->register(self::FLOOD_NAME, self::FLOOD_WINDOW, $ip);
    $cep = (string) $request->query->get('cep', '');
    $variation = $this->variation((int) $request->query->get('variation', 0));
    // Com produto, usa o pacote da variação (padrão como mínimo). Sem produto, cota o carrinho inteiro: cada item
    // vai à API com a própria medida e quantidade.
    $result = $variation !== NULL
      ? $this->simulator->simulateForVariation($cep, $variation)
      : $this->simulator->simulateForCartLines($cep, $this->cartLines->lines());
    // Guarda o cálculo na sessão para o produto e o carrinho (o carrinho lateral só lê). CEP inválido não é guardado.
    $digits = preg_replace('/\D/', '', $cep) ?? '';
    if (strlen($digits) === 8) {
      $this->store->save($digits, $result);
    }
    return $this->json($result->toArray());
  }

  /**
   * Resposta sem cache: o resultado depende do CEP e do estado da configuração.
   *
   * @param array<string, mixed> $data
   *   Corpo JSON.
   * @param int $status
   *   Status HTTP.
   */
  private function json(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

  /**
   * Variação de produto da loja (produto_loja), publicada. NULL para id ausente ou inválido.
   */
  private function variation(int $id): ?ProductVariationInterface {
    if ($id <= 0) {
      return NULL;
    }
    $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->load($id);
    if (!$variation instanceof ProductVariationInterface || !$variation->isPublished()) {
      return NULL;
    }
    $product = $variation->getProduct();
    return $product !== NULL && $product->bundle() === 'produto_loja' ? $variation : NULL;
  }

}
