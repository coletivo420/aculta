<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Erro do cliente do Melhor Envio.
 *
 * A mensagem é sempre uma constante em português, sem corpo de resposta, token ou credencial.
 * A exceção original, se houver, fica só em getPrevious() para depuração local.
 */
final class MelhorEnvioException extends \RuntimeException {

  /**
   * Cria a exceção com categoria, mensagem fixa e status opcional.
   *
   * @param string $category
   *   Categoria: unauthenticated, unauthorized, forbidden, not_found, validation, rate_limited, server, network,
   *   invalid_response, not_configured ou unexpected.
   * @param string $message
   *   Mensagem fixa, sem corpo de resposta nem credencial.
   * @param int $statusCode
   *   Status HTTP recebido, ou 0 quando não houve resposta.
   * @param \Throwable|null $previous
   *   Exceção original, quando houver.
   */
  public function __construct(
    private readonly string $category,
    string $message,
    private readonly int $statusCode = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, 0, $previous);
  }

  /**
   * Categoria do erro, como unauthorized, rate_limited ou network.
   *
   * @return string
   *   Texto.
   */
  public function category(): string {
    return $this->category;
  }

  /**
   * Status HTTP recebido, ou 0 quando não houve resposta.
   *
   * @return int
   *   Número inteiro.
   */
  public function statusCode(): int {
    return $this->statusCode;
  }

}
