<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Guarda, na mesma requisição, o código e o texto completo de cada falha de transporte, por correlação.
 *
 * A camada de envio E-Mails ACULTA captura a exceção do transporte e devolve só FALSE; o subscriber de FailedMessageEvent registra a falha
 * aqui antes, e o AcultaMailer a consome logo após a chamada. Não persiste nada.
 */
final class SmtpFailureRegistry {

  /**
   * Falhas por correlação: código (NULL quando o transporte não informou) e texto completo.
   *
   * @var array<string, array{code: int|null, message: string}>
   */
  private array $failures = [];

  /**
   * Registra a falha de transporte da mensagem.
   */
  public function remember(string $correlationId, ?int $code, string $message): void {
    $this->failures[$correlationId] = ['code' => $code, 'message' => $message];
  }

  /**
   * Consome o registro da falha da mensagem.
   *
   * @return array|null
   *   ['code' => ?int, 'message' => string] se houve falha, ou NULL.
   */
  public function take(string $correlationId): ?array {
    if (!array_key_exists($correlationId, $this->failures)) {
      return NULL;
    }
    $failure = $this->failures[$correlationId];
    unset($this->failures[$correlationId]);
    return $failure;
  }

}
