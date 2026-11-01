<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Guarda, na mesma requisição, o conteúdo de cada mensagem enviada, por correlação, para o caso de falha.
 *
 * O plugin de envio registra o conteúdo antes de enviar; o AcultaMailer consome ao final. Só a falha leva o conteúdo ao
 * registro, cifrado (ver MailPayloadCipher). Nada é persistido aqui.
 */
final class MailPayloadRegistry {

  /**
   * Conteúdo por correlação.
   *
   * @var array<string, array>
   */
  private array $payloads = [];

  /**
   * Registra o conteúdo da mensagem.
   */
  public function remember(string $correlationId, array $payload): void {
    $this->payloads[$correlationId] = $payload;
  }

  /**
   * Consome o conteúdo da mensagem, ou NULL quando não foi registrado.
   */
  public function take(string $correlationId): ?array {
    if (!isset($this->payloads[$correlationId])) {
      return NULL;
    }
    $payload = $this->payloads[$correlationId];
    unset($this->payloads[$correlationId]);
    return $payload;
  }

}
