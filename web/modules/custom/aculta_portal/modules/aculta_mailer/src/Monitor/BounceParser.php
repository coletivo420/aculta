<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Notificação de falha de entrega (DSN, RFC 3464): status, ação e o correlation ID da mensagem original.
 *
 * O correlation ID vem do cabeçalho X-Aculta-Mail-ID, que o envio grava e que o servidor devolve dentro da mensagem
 * original anexada à notificação.
 */
final class BounceParser {

  /**
   * Dados da falha, ou NULL quando a mensagem não é uma notificação de falha.
   *
   * @return array{status: string, action: string, diagnostic: string, recipient: string, correlation: string|null}|null
   *   Resultado do processamento.
   */
  public function parse(string $raw): ?array {
    $status = NULL;
    $correlation = NULL;
    foreach (MimeMessage::leaves($raw) as $leaf) {
      if ($leaf['type'] === 'message/delivery-status' && $status === NULL) {
        $status = $this->firstRecipientBlock($leaf['body']);
      }
      if (in_array($leaf['type'], ['message/rfc822', 'text/rfc822-headers'], TRUE) && $correlation === NULL) {
        $correlation = MimeMessage::topHeaders($leaf['body'])['x-aculta-mail-id'] ?? NULL;
      }
    }
    if ($status === NULL) {
      return NULL;
    }
    return $status + ['correlation' => $correlation];
  }

  /**
   * Campos do primeiro destinatário com status, lidos do bloco de campos por destinatário.
   *
   * @return array{status: string, action: string, diagnostic: string, recipient: string}|null
   *   Resultado do processamento.
   */
  private function firstRecipientBlock(string $body): ?array {
    foreach (preg_split('/\r?\n\s*\r?\n/', trim($body)) ?: [] as $block) {
      $fields = MimeMessage::headers($block);
      if (!isset($fields['status'])) {
        continue;
      }
      return [
        'status' => $fields['status'],
        'action' => strtolower($fields['action'] ?? ''),
        'diagnostic' => $fields['diagnostic-code'] ?? '',
        'recipient' => $fields['final-recipient'] ?? '',
      ];
    }
    return NULL;
  }

}
