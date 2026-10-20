<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Guarda, na mesma requisição, o ID que o provedor deu a cada mensagem aceita, por correlação.
 *
 * O plugin de envio recebe só o resultado booleano do Core; o AcultaMailer consome o ID logo após a chamada e o grava no
 * registro. Não persiste nada: se a requisição acabar antes, o registro fica sem o ID, e o correlation ID continua valendo.
 */
final class ProviderMessageRegistry {

  /**
   * IDs de provedor por correlação.
   *
   * @var array<string, string>
   */
  private array $ids = [];

  /**
   * Registra o ID do provedor da mensagem aceita.
   */
  public function remember(string $correlationId, string $providerMessageId): void {
    $this->ids[$correlationId] = $providerMessageId;
  }

  /**
   * Consome o ID do provedor da mensagem, ou NULL quando não foi informado.
   */
  public function take(string $correlationId): ?string {
    if (!isset($this->ids[$correlationId])) {
      return NULL;
    }
    $id = $this->ids[$correlationId];
    unset($this->ids[$correlationId]);
    return $id;
  }

}
