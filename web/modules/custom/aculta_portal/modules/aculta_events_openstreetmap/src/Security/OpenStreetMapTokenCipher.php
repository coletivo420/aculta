<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

/**
 * Ciphers OAuth access tokens before they enter State API storage.
 */
final class OpenStreetMapTokenCipher {

  private const PREFIX = 'v1:';

  /**
   * Recebe a fonte única da chave de cifra por ambiente.
   */
  public function __construct(private readonly OpenStreetMapCredentialProviderInterface $credentials) {}

  /**
   * Cifra um token sem armazenar a chave no banco ou na configuração. */
  public function encrypt(string $plain): string {
    $key = $this->key() ?? throw new \RuntimeException('OpenStreetMap token encryption is unavailable.');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
  }

  /**
   * Decifra somente payloads válidos autenticados com secretbox. */
  public function decrypt(string $stored): string {
    $key = $this->key() ?? throw new \RuntimeException('OpenStreetMap token encryption is unavailable.');
    if (!str_starts_with($stored, self::PREFIX)) {
      throw new \RuntimeException('Unknown OpenStreetMap token encoding.');
    }
    $raw = base64_decode(substr($stored, strlen(self::PREFIX)), TRUE);
    if ($raw === FALSE || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      throw new \RuntimeException('Malformed OpenStreetMap token value.');
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    if ($plain === FALSE) {
      throw new \RuntimeException('OpenStreetMap token authentication failed.');
    }
    return $plain;
  }

  /**
   * Retorna chave válida de 32 bytes ou NULL. */
  private function key(): ?string {
    $hex = $this->credentials->tokenEncryptionKey();
    if (!is_string($hex) || strlen($hex) !== 2 * SODIUM_CRYPTO_SECRETBOX_KEYBYTES || !ctype_xdigit($hex)) {
      return NULL;
    }
    return hex2bin($hex) ?: NULL;
  }

}
