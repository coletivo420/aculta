<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

use Drupal\Core\State\StateInterface;

/**
 * Armazena tokens no State API, uma entrada por ambiente.
 *
 * O State não entra na exportação de configuração. A criptografia em repouso depende da política de
 * produção (chaves no banco criptografado) e está registrada como pendência ME-PEND-02.
 */
final class StateMelhorEnvioTokenStore implements MelhorEnvioTokenStoreInterface {

  private const PREFIX = 'aculta_commerce_melhorenvio.tokens.';

  public function __construct(
    private readonly StateInterface $state,
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
    $tokens = $this->state->get(self::PREFIX . $environment->value);
    if (!is_array($tokens) || !isset($tokens['access_token'], $tokens['refresh_token'], $tokens['expires_at'])) {
      return NULL;
    }
    return [
      'access_token' => (string) $tokens['access_token'],
      'refresh_token' => (string) $tokens['refresh_token'],
      'expires_at' => (int) $tokens['expires_at'],
    ];
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
    $this->state->set(self::PREFIX . $environment->value, $tokens);
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
