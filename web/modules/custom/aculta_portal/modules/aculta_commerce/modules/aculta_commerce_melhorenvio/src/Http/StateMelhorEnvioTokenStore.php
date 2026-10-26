<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

use Drupal\Core\State\StateInterface;

/**
 * Armazena tokens cifrados no State API, uma entrada por ambiente.
 *
 * O payload usa uma chave própria resolvida pelo contrato central de segredos.
 */
final class StateMelhorEnvioTokenStore implements MelhorEnvioTokenStoreInterface {

  private const PREFIX = 'aculta_commerce_melhorenvio.tokens.';

  public function __construct(
    private readonly StateInterface $state,
    private readonly MelhorEnvioTokenCipher $cipher,
  ) {}

  /**
   * Tokens do ambiente informado, ou NULL quando não há.
   *
   * @param MelhorEnvioEnvironment $environment
   *   Ambiente.
   *
   * @return array{access_token:
   *   string, refresh_token: string, expires_at: int}|null
   */
  public function load(MelhorEnvioEnvironment $environment): ?array {
    $stored = $this->state->get(self::PREFIX . $environment->value);
    if (!is_array($stored) || ($stored['format'] ?? NULL) !== 'encrypted_v1' || !is_string($stored['payload'] ?? NULL)) {
      return NULL;
    }
    try {
      return $this->decode($this->cipher->decrypt($stored['payload']));
    }
    catch (\RuntimeException) {
      // Falha fechada: chave ausente, valor adulterado ou formato inválido equivalem a "sem tokens".
      // O usuário precisa autorizar de novo. Nenhum texto puro é devolvido nem registrado.
      return NULL;
    }
  }

  /**
   * Grava os tokens do ambiente informado.
   *
   * @param MelhorEnvioEnvironment $environment
   *   Ambiente.
   * @param array{access_token: string, refresh_token: string, expires_at: int} $tokens
   *   Tokens a gravar.
   */
  public function save(MelhorEnvioEnvironment $environment, array $tokens): void {
    $json = json_encode($this->normalize($tokens), JSON_THROW_ON_ERROR);
    $this->state->set(self::PREFIX . $environment->value, [
      'format' => 'encrypted_v1',
      'payload' => $this->cipher->encrypt($json),
    ]);
  }

  /**
   * Encrypts legacy plaintext State records after the key has been provisioned.
   *
   * @return int
   *   Number of environment records migrated.
   *
   * @throws \RuntimeException
   *   When a legacy record is malformed or the encryption key is unavailable.
   */
  public function migrateLegacyTokens(): int {
    $migrated = 0;
    foreach (MelhorEnvioEnvironment::cases() as $environment) {
      $key = self::PREFIX . $environment->value;
      $stored = $this->state->get($key);
      if (!is_array($stored) || ($stored['format'] ?? NULL) === 'encrypted_v1') {
        continue;
      }
      $json = json_encode($this->normalize($stored), JSON_THROW_ON_ERROR);
      $this->state->set($key, [
        'format' => 'encrypted_v1',
        'payload' => $this->cipher->encrypt($json),
      ]);
      $migrated++;
    }
    return $migrated;
  }

  /**
   * Decodes and validates an encrypted JSON token payload.
   */
  private function decode(string $json): array {
    try {
      $tokens = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \RuntimeException('Malformed Melhor Envio token payload.');
    }
    return $this->normalize(is_array($tokens) ? $tokens : []);
  }

  /**
   * Validates token payload fields without exposing their values.
   */
  private function normalize(array $tokens): array {
    if (!is_string($tokens['access_token'] ?? NULL) || $tokens['access_token'] === ''
      || !is_string($tokens['refresh_token'] ?? NULL) || $tokens['refresh_token'] === ''
      || !is_int($tokens['expires_at'] ?? NULL) || $tokens['expires_at'] < 1) {
      throw new \RuntimeException('Malformed Melhor Envio token payload.');
    }
    return [
      'access_token' => $tokens['access_token'],
      'refresh_token' => $tokens['refresh_token'],
      'expires_at' => $tokens['expires_at'],
    ];
  }

  /**
   * Remove os tokens do ambiente informado.
   *
   * @param MelhorEnvioEnvironment $environment
   *   Ambiente.
   */
  public function clear(MelhorEnvioEnvironment $environment): void {
    $this->state->delete(self::PREFIX . $environment->value);
  }

}
