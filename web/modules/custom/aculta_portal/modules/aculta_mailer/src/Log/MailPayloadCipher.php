<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Log;

use Drupal\aculta_mailer\Transport\SecretProviderInterface;

/**
 * Cifra o conteúdo de uma mensagem com falha, para o reenvio, com a chave MAILER_PAYLOAD_KEY (32 bytes em base64).
 *
 * `sodium_crypto_secretbox` (XSalsa20 e Poly1305), com nonce aleatório a cada cifragem. O valor guardado começa com a
 * versão do formato, para permitir troca de algoritmo sem perder os registros antigos. Sem chave válida, nada é cifrado
 * e o reenvio fica indisponível: quem não tem a chave não guarda o conteúdo.
 */
final class MailPayloadCipher {

  /**
   * Versão do formato guardado.
   */
  private const VERSION = 'v1';

  public function __construct(
    private readonly ?SecretProviderInterface $secrets = NULL,
  ) {}

  /**
   * Cifra a mensagem. Retorna NULL quando não há chave válida.
   *
   * @param array $payload
   *   Dados da mensagem (destinatário, assunto, corpo e cabeçalhos).
   */
  public function encrypt(array $payload): ?string {
    $key = $this->key();
    if ($key === NULL) {
      return NULL;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === FALSE) {
      return NULL;
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($json, $nonce, $key);
    sodium_memzero($key);
    return self::VERSION . '.' . base64_encode($nonce . $cipher);
  }

  /**
   * Decifra a mensagem guardada. Retorna NULL quando a chave não confere, o valor foi alterado ou o formato não é o atual.
   */
  public function decrypt(string $stored): ?array {
    $key = $this->key();
    if ($key === NULL || !str_starts_with($stored, self::VERSION . '.')) {
      return NULL;
    }
    $raw = base64_decode(substr($stored, strlen(self::VERSION) + 1), TRUE);
    if ($raw === FALSE || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      return NULL;
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    sodium_memzero($key);
    if ($plain === FALSE) {
      return NULL;
    }
    $data = json_decode($plain, TRUE);
    return is_array($data) ? $data : NULL;
  }

  /**
   * Chave de 32 bytes do segredo, ou NULL quando ausente ou com tamanho errado.
   */
  private function key(): ?string {
    $value = (string) ($this->secrets?->value('MAILER_PAYLOAD_KEY') ?? '');
    if ($value === '') {
      return NULL;
    }
    $key = base64_decode($value, TRUE);
    return $key !== FALSE && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $key : NULL;
  }

}
