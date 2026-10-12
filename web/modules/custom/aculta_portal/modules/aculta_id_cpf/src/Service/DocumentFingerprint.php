<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_id_cpf\Enum\DocumentType;
use Drupal\key\KeyRepositoryInterface;

/**
 * Creates keyed, deterministic document fingerprints for uniqueness indexes.
 *
 * A fingerprint is pseudonymous personal data, not anonymized data. Callers
 * must keep it in protected storage and must not expose it to users or logs.
 */
final class DocumentFingerprint {

  /**
   * Machine name of the Key entity that contains the site-specific HMAC key.
   */
  public const KEY_ID = 'aculta_id_cpf_fingerprint';

  /**
   * Minimum key strength: 256 bits.
   */
  private const MINIMUM_KEY_BYTES = 32;

  public function __construct(
    private readonly DocumentValidator $validator,
    private readonly KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * Returns a stable SHA-256 HMAC for a valid CPF or CNPJ.
   *
   * The document type and version are included in the message so identical
   * bytes in different namespaces cannot produce the same fingerprint.
   * Fingerprints remain stable only while the configured key remains stable.
   *
   * @throws \InvalidArgumentException
   *   When the supplied document is invalid.
   * @throws \RuntimeException
   *   When the configured Key is unavailable or too short.
   */
  public function fingerprint(string $input, DocumentType $type): string {
    $canonical = $this->validator->canonicalize($input, $type);
    $secret = $this->secret();
    if ($secret === NULL) {
      throw new \RuntimeException('The document fingerprint key is unavailable or invalid.');
    }

    $message = "aculta_id_cpf:v1\0" . $type->value . "\0" . $canonical;
    return hash_hmac('sha256', $message, $secret);
  }

  /** TRUE when the Key exists and meets the minimum strength; never exposes the value. */
  public function isAvailable(): bool {
    return $this->secret() !== NULL;
  }

  private function secret(): ?string {
    $secret = $this->keyRepository->getKey(self::KEY_ID)?->getKeyValue(TRUE);
    return is_string($secret) && strlen($secret) >= self::MINIMUM_KEY_BYTES ? $secret : NULL;
  }

}
