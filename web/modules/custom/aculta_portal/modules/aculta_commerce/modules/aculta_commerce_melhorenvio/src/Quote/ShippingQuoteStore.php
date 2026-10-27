<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Último cálculo de frete do visitante, guardado na sessão.
 *
 * É o que faz o cálculo "ir junto" do produto para o carrinho: a página do produto e o carrinho leem o mesmo resultado.
 * Guarda só o CEP normalizado e o resultado já formatado (sem dados pessoais além do CEP), por TTL curto. O carrinho
 * lateral só lê; quem calcula é a página do produto ou o carrinho completo.
 */
class ShippingQuoteStore {

  /**
   * Chave na sessão.
   */
  private const KEY = 'aculta_commerce_melhorenvio.frete';

  /**
   * Validade do cálculo guardado, em segundos.
   */
  public const TTL = 900;

  /**
   * Cria o armazenamento.
   */
  public function __construct(
    private readonly RequestStack $requests,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Guarda o resultado do cálculo para o CEP informado.
   *
   * @param string $postalCode
   *   CEP normalizado (8 dígitos).
   * @param \Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation $result
   *   Resultado do simulador.
   */
  public function save(string $postalCode, ShippingSimulation $result): void {
    $session = $this->session();
    if ($session === NULL) {
      return;
    }
    $session->set(self::KEY, [
      'postal_code' => $postalCode,
      'at' => $this->time->getRequestTime(),
      'result' => $result->toArray(),
    ]);
  }

  /**
   * Cálculo ainda válido da sessão, ou NULL quando não há ou expirou.
   *
   * @return array{postal_code: string, at: int, result: array<string, mixed>}|null
   */
  public function load(): ?array {
    $session = $this->session();
    $stored = $session?->get(self::KEY);
    if (!is_array($stored) || !isset($stored['postal_code'], $stored['at'], $stored['result'])) {
      return NULL;
    }
    if ($this->time->getRequestTime() - (int) $stored['at'] > self::TTL) {
      $session?->remove(self::KEY);
      return NULL;
    }
    return $stored;
  }

  /**
   * Sessão da requisição atual; NULL em contexto sem sessão (linha de comando, cache).
   */
  private function session(): ?SessionInterface {
    $request = $this->requests->getCurrentRequest();
    return $request !== NULL && $request->hasSession() ? $request->getSession() : NULL;
  }

}
