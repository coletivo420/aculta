<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Queue;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Core\Queue\QueueFactory;

/**
 * Enfileira mensagens para envio assíncrono pelo worker `aculta_mailer_send`.
 *
 * Consumidores que não precisam de resposta imediata (cadastro, comentário, curso) usam esta fila, e o envio não
 * espera a latência do SMTP. A fila guarda destinatário e contexto, nunca o corpo renderizado.
 */
final class MailQueue {

  public const QUEUE_NAME = 'aculta_mailer_send';

  /**
   * Transporte da primeira tentativa: o padrão de envio (Hostinger, ID `smtp`).
   */
  public const DEFAULT_TRANSPORT = 'smtp';

  public function __construct(
    private readonly QueueFactory $queues,
  ) {}

  /**
   * Coloca a mensagem na fila, na primeira tentativa, sem atraso.
   */
  public function enqueue(MailMessage $message): void {
    $this->queues->get(self::QUEUE_NAME)->createItem([
      'message' => $message->toArray(),
      'attempt' => 1,
      'not_before' => 0,
      // NULL: a política escolhe o transporte na primeira tentativa; cai no padrão se não houver ajuste.
      'transport' => NULL,
    ]);
  }

}
