<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Controller;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
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
    $result = $this->simulator->simulate((string) $request->query->get('cep', ''), NULL);
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

}
