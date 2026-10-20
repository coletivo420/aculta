<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Lista de supressão: destinatários que não recebem mais e-mails do sistema.
 *
 * Entram por devolução permanente, endereço inválido ou reclamação de spam (ver DeliveryEventApplier). Guarda só o HMAC
 * do endereço, o mesmo do registro de envio, e a data em que entrou. Limpar a lista é ação do administrador.
 */
final class MailSuppression {

  /**
   * Chave de estado: HMAC do destinatário => instante em que entrou na lista.
   */
  public const STATE = 'aculta_mailer.suppressed';

  public function __construct(
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Inclui o destinatário (HMAC) na lista. Repetir não altera a data original.
   */
  public function add(string $recipientHash): void {
    $list = $this->list();
    if (isset($list[$recipientHash])) {
      return;
    }
    $list[$recipientHash] = $this->time->getRequestTime();
    $this->state->set(self::STATE, $list);
  }

  /**
   * Indica se o destinatário (HMAC) está suprimido.
   */
  public function has(string $recipientHash): bool {
    return isset($this->list()[$recipientHash]);
  }

  /**
   * Quantidade de endereços na lista.
   */
  public function count(): int {
    return count($this->list());
  }

  /**
   * Esvazia a lista. Ação do administrador, pelo formulário de entrega.
   */
  public function clear(): void {
    $this->state->delete(self::STATE);
  }

  /**
   * Lista atual, sem tocar no estado quando vazia.
   *
   * @return array<string, int>
   *   HMAC => instante.
   */
  private function list(): array {
    $list = $this->state->get(self::STATE, []);
    return is_array($list) ? $list : [];
  }

}
