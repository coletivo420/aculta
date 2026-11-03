<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Cliente IMAP sobre a extensão curl do PHP (protocolo imaps). Não usa biblioteca nova.
 *
 * O progresso é controlado por UID (cursor no State), então o sinalizador \Seen que o FETCH possa marcar não afeta a leitura.
 */
final class CurlImapClient implements ImapClientInterface {

  /**
   * Teto do download de uma mensagem (25 MiB), medido enquanto a transferência corre.
   */
  public const MAX_MESSAGE_BYTES = 26214400;

  public function __construct(
    private readonly string $host,
    private readonly int $port,
    private readonly string $user,
    private readonly string $password,
    private readonly string $mailbox,
    private readonly int $maxMessageBytes = self::MAX_MESSAGE_BYTES,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function uidsAbove(int $uid): array {
    $response = $this->request($this->mailboxUrl(), 'UID SEARCH UID ' . ($uid + 1) . ':*');
    return self::parseSearch($response, $uid);
  }

  /**
   * UIDs maiores que o informado, da resposta `* SEARCH`.
   *
   * O servidor termina as linhas com CRLF: a linha de resposta tem de aceitar o retorno de carro antes do fim.
   *
   * @return int[]
   *   UIDs em ordem crescente, sem repetição.
   */
  public static function parseSearch(string $response, int $uid): array {
    preg_match_all('/^\* SEARCH([\d ]*)\r?$/m', $response, $matches);
    $uids = [];
    foreach ($matches[1] as $line) {
      foreach (array_filter(explode(' ', trim($line)), 'strlen') as $candidate) {
        if ((int) $candidate > $uid) {
          $uids[] = (int) $candidate;
        }
      }
    }
    sort($uids);
    return array_values(array_unique($uids));
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(int $uid): string {
    return $this->request($this->mailboxUrl() . ';UID=' . $uid, NULL, $this->maxMessageBytes);
  }

  /**
   * Requisição curl com credenciais, em modo de transferência de dados. Falha é exceção.
   *
   * Com $maxBytes, a transferência é cortada ao passar do teto, sem acumular o restante em memória.
   */
  private function request(string $url, ?string $customCommand = NULL, ?int $maxBytes = NULL): string {
    $handle = curl_init($url);
    if ($handle === FALSE) {
      throw new \RuntimeException('Não foi possível iniciar a conexão IMAP.');
    }
    $buffer = '';
    $received = 0;
    $overflow = FALSE;
    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => $maxBytes === NULL,
      CURLOPT_USERNAME => $this->user,
      CURLOPT_PASSWORD => $this->password,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($maxBytes !== NULL) {
      curl_setopt($handle, CURLOPT_WRITEFUNCTION, static function ($curlHandle, string $chunk) use (&$buffer, &$received, &$overflow, $maxBytes): int {
        $received += strlen($chunk);
        if ($received > $maxBytes) {
          // Retornar um valor menor que o recebido aborta a transferência.
          $overflow = TRUE;
          return 0;
        }
        $buffer .= $chunk;
        return strlen($chunk);
      });
    }
    if ($customCommand !== NULL) {
      curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $customCommand);
    }
    $body = curl_exec($handle);
    $error = curl_error($handle);
    if ($overflow) {
      throw new ImapMessageTooLargeException('Mensagem IMAP acima do limite de download.');
    }
    if ($body === FALSE) {
      // A mensagem do erro não inclui credenciais (o curl não as ecoa).
      throw new \RuntimeException('Falha na leitura IMAP: ' . $error);
    }
    return $maxBytes === NULL ? (string) $body : $buffer;
  }

  /**
   * URL da caixa, com porta e pasta.
   */
  private function mailboxUrl(): string {
    return sprintf('imaps://%s:%d/%s', $this->host, $this->port, rawurlencode($this->mailbox));
  }

}
