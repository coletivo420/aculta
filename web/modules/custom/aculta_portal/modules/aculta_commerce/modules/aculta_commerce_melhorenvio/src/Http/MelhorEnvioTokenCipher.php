<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

use Drupal\aculta_portal\Secrets\SecretsManager;

/**
 * Encrypts OAuth token payloads with the centrally managed Melhor Envio key.
 */
final class MelhorEnvioTokenCipher {

  private const KEY_ID = 'aculta_melhorenvio_token_encryption';

  private const PREFIX = 'v1:';

  public function __construct(private readonly SecretsManager $secrets) {}

  /**
   * Encrypts a payload using authenticated secretbox encryption.
   *
   * @throws \RuntimeException
   *   When the encryption key is unavailable.
   */
  public function encrypt(string $plain): string {
    $key = $this->key() ?? throw new \RuntimeException('Melhor Envio token encryption is unavailable.');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
  }

  /**
   * Decrypts an authenticated token payload.
   *
   * @throws \RuntimeException
   *   When the key or stored value is unavailable or invalid.
   */
  public function decrypt(string $stored): string {
    $key = $this->key() ?? throw new \RuntimeException('Melhor Envio token encryption is unavailable.');
    if (!str_starts_with($stored, self::PREFIX)) {
      throw new \RuntimeException('Unknown Melhor Envio token encoding.');
    }
    $raw = base64_decode(substr($stored, strlen(self::PREFIX)), TRUE);
    if ($raw === FALSE || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      throw new \RuntimeException('Malformed Melhor Envio token value.');
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    if ($plain === FALSE) {
      throw new \RuntimeException('Melhor Envio token authentication failed.');
    }
    return $plain;
  }

  /**
   * Chave de 32 bytes (64 caracteres hex), ou NULL se ausente ou malformada.
   */
  private function key(): ?string {
    $hex = $this->secrets->valueForKeyId(self::KEY_ID);
    if (!is_string($hex) || strlen($hex) !== 2 * SODIUM_CRYPTO_SECRETBOX_KEYBYTES || !ctype_xdigit($hex)) {
      return NULL;
    }
    return hex2bin($hex) ?: NULL;
  }

}
