<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Cliente IMAP sobre a extensão curl do PHP (protocolo imaps). Não usa biblioteca nova.
 *
 * O progresso é controlado por UID (cursor no State), então o sinalizador \Seen que o FETCH possa marcar não afeta a leitura.
 */
final class CurlImapClient implements ImapClientInterface {

  public function __construct(
    private readonly string $host,
    private readonly int $port,
    private readonly string $user,
    private readonly string $password,
    private readonly string $mailbox,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function uidsAbove(int $uid): array {
    $response = $this->request($this->mailboxUrl(), 'UID SEARCH UID ' . ($uid + 1) . ':*');
    preg_match_all('/^\* SEARCH([\d ]*)$/m', $response, $matches);
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
    return $this->request($this->mailboxUrl() . ';UID=' . $uid);
  }

  /**
   * Requisição curl com credenciais, em modo de transferência de dados. Falha é exceção.
   */
  private function request(string $url, ?string $customCommand = NULL): string {
    $handle = curl_init($url);
    if ($handle === FALSE) {
      throw new \RuntimeException('Não foi possível iniciar a conexão IMAP.');
    }
    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_USERNAME => $this->user,
      CURLOPT_PASSWORD => $this->password,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($customCommand !== NULL) {
      curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $customCommand);
    }
    $body = curl_exec($handle);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === FALSE) {
      // A mensagem do erro não inclui credenciais (o curl não as ecoa).
      throw new \RuntimeException('Falha na leitura IMAP: ' . $error);
    }
    return (string) $body;
  }

  /**
   * URL da caixa, com porta e pasta.
   */
  private function mailboxUrl(): string {
    return sprintf('imaps://%s:%d/%s', $this->host, $this->port, rawurlencode($this->mailbox));
  }

}
