<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Último cálculo de frete, guardado na sessão.
 *
 * Faz o cálculo "ir junto" do produto para o carrinho: ambos leem o mesmo
 * resultado. Guarda o CEP normalizado e o resultado formatado por TTL curto.
 * O carrinho lateral só lê; a página do produto ou o carrinho completo calcula.
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
    private readonly ShippingPostalCodeStore $postalCodes,
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
    $postalCode = $this->postalCodes->save($postalCode);
    if ($postalCode === NULL) {
      return;
    }
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
   *   Stored calculation data, or NULL when missing or expired.
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
   * Loads the last CEP, or the authenticated user's saved address CEP.
   */
  public function loadLastPostalCode(): ?string {
    return $this->postalCodes->load();
  }

  /**
   * Sessão da requisição atual, ou NULL em contexto sem sessão.
   */
  private function session(): ?SessionInterface {
    $request = $this->requests->getCurrentRequest();
    return $request !== NULL && $request->hasSession() ? $request->getSession() : NULL;
  }

}
