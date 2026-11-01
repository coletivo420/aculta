<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

use Drupal\Core\State\StateInterface;

/**
 * Stores an encrypted OSM token separately for test and production.
 */
final class OpenStreetMapTokenStore {

  private const PREFIX = 'aculta_events_openstreetmap.oauth_token.';

  /**
   * Combina State API, cifra autenticada e escopo do ambiente ativo.
   */
  public function __construct(
    private readonly StateInterface $state,
    private readonly OpenStreetMapTokenCipher $cipher,
    private readonly OpenStreetMapEnvironment $environment,
  ) {}

  /**
   * Carrega token cifrado apenas para o ambiente efetivo. */
  public function load(): ?string {
    $environment = $this->environment->environment();
    if ($environment === NULL) {
      return NULL;
    }
    $stored = $this->state->get(self::PREFIX . $environment);
    if (!is_array($stored) || ($stored['format'] ?? NULL) !== 'encrypted_v1'
      || !is_string($stored['payload'] ?? NULL)) {
      return NULL;
    }
    try {
      $token = $this->cipher->decrypt($stored['payload']);
      return $token === '' ? NULL : $token;
    }
    catch (\RuntimeException) {
      return NULL;
    }
  }

  /**
   * Armazena token cifrado e rejeita ambiente incompatível. */
  public function save(string $token): void {
    $environment = $this->environment->environment();
    if ($environment === NULL || $token === '' || strlen($token) > 4096) {
      throw new \RuntimeException('OpenStreetMap token cannot be stored for this environment.');
    }
    $this->state->set(self::PREFIX . $environment, [
      'format' => 'encrypted_v1',
      'payload' => $this->cipher->encrypt($token),
    ]);
  }

  /**
   * Remove token somente do ambiente ativo. */
  public function clear(): void {
    $environment = $this->environment->environment();
    if ($environment !== NULL) {
      $this->state->delete(self::PREFIX . $environment);
    }
  }

  /**
   * Informa se existe um token válido para o ambiente ativo. */
  public function isConnected(): bool {
    return $this->load() !== NULL;
  }

}
