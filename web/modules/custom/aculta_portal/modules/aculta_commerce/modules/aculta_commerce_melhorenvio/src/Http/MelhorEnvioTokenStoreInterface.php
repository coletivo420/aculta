<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Guarda os tokens OAuth2 do Melhor Envio separados por ambiente.
 *
 * Tokens nunca vão para código nem para configuração exportada. Cada ambiente tem o seu.
 */
interface MelhorEnvioTokenStoreInterface {

  /**
   * Tokens do ambiente informado, ou NULL quando não há.
   *
   * @param MelhorEnvioEnvironment $environment
   *   Ambiente.
   *
   * @return array{access_token:
   *   string, refresh_token: string, expires_at: int}|null
   */
  public function load(MelhorEnvioEnvironment $environment): ?array;

  /**
   * Grava os tokens do ambiente informado.
   *
   * @param MelhorEnvioEnvironment $environment
   *   Ambiente.
   * @param array{access_token: string, refresh_token: string, expires_at: int} $tokens
   *   Tokens a gravar.
   */
  public function save(MelhorEnvioEnvironment $environment, array $tokens): void;

  /**
   * Remove os tokens do ambiente informado.
   *
   * @param MelhorEnvioEnvironment $environment
   *   Ambiente.
   */
  public function clear(MelhorEnvioEnvironment $environment): void;

}
