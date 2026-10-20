<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Guarda, na mesma requisição, o código SMTP de cada falha de transporte, por correlação.
 *
 * O Mailer Plus captura a exceção do transporte e devolve só FALSE; o subscriber de FailedMessageEvent registra o
 * código aqui antes, e o AcultaMailer o consome logo após a chamada. Não persiste nada.
 */
final class SmtpFailureRegistry {

  /**
   * Códigos por correlação; NULL quando o transporte não informou código.
   *
   * @var array<string, int|null>
   */
  private array $codes = [];

  /**
   * Registra a falha de transporte da mensagem.
   */
  public function remember(string $correlationId, ?int $code): void {
    $this->codes[$correlationId] = $code;
  }

  /**
   * Consome o registro da falha da mensagem.
   *
   * @return array|null
   *   ['code' => ?int] se houve falha, ou NULL.
   */
  public function take(string $correlationId): ?array {
    if (!array_key_exists($correlationId, $this->codes)) {
      return NULL;
    }
    $code = $this->codes[$correlationId];
    unset($this->codes[$correlationId]);
    return ['code' => $code];
  }

}
