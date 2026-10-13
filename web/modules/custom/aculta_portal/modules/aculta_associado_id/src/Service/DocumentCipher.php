<?php

declare(strict_types=1);

namespace Drupal\aculta_associado_id\Service;

use Drupal\key\KeyRepositoryInterface;

/**
 * Symmetric encryption for stored documents, with the key resolved through Drupal Key.
 *
 * The key is a 32-byte value written as 64 hexadecimal characters in the environment variable of
 * the `aculta_document_encryption_key` Key. Without it, storage is unavailable and nothing is
 * written in clear. Errors never include the plaintext or the key.
 */
final class DocumentCipher {

  private const KEY_ID = 'aculta_document_encryption_key';

  private const PREFIX = 'v1:';

  public function __construct(private readonly KeyRepositoryInterface $keys) {}

  public function isAvailable(): bool {
    return $this->key() !== NULL;
  }

  /**
   * @throws \RuntimeException
   *   When the key is unavailable.
   */
  public function encrypt(string $plain): string {
    $key = $this->key() ?? throw new \RuntimeException('Document encryption is unavailable.');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
  }

  /**
   * @throws \RuntimeException
   *   When the key is unavailable, the value is malformed or authentication fails.
   */
  public function decrypt(string $stored): string {
    $key = $this->key() ?? throw new \RuntimeException('Document encryption is unavailable.');
    if (!str_starts_with($stored, self::PREFIX)) {
      throw new \RuntimeException('Unknown document encoding.');
    }
    $raw = base64_decode(substr($stored, strlen(self::PREFIX)), TRUE);
    if ($raw === FALSE || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      throw new \RuntimeException('Malformed document value.');
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    if ($plain === FALSE) {
      throw new \RuntimeException('Document authentication failed.');
    }
    return $plain;
  }

  private function key(): ?string {
    $hex = $this->keys->getKey(self::KEY_ID)?->getKeyValue();
    if (!is_string($hex) || strlen($hex) !== 2 * SODIUM_CRYPTO_SECRETBOX_KEYBYTES || !ctype_xdigit($hex)) {
      return NULL;
    }
    return hex2bin($hex);
  }

}
