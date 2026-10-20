<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Leitura de uma caixa IMAP: UIDs com número maior que um limite, e o conteúdo de uma mensagem.
 */
interface ImapClientInterface {

  /**
   * UIDs de mensagens com número maior que o informado, em ordem crescente.
   *
   * @return int[]
   *   UIDs encontrados.
   */
  public function uidsAbove(int $uid): array;

  /**
   * Conteúdo bruto (RFC 5322) da mensagem, sem marcá-la como lida.
   */
  public function fetch(int $uid): string;

}
