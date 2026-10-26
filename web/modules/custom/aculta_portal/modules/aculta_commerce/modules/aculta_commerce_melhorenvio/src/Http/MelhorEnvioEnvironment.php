<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Ambientes do Melhor Envio. Sandbox e produção têm cadastro, tokens e dados separados.
 */
enum MelhorEnvioEnvironment: string {

  case Sandbox = 'sandbox';
  case Production = 'production';

  /**
   * URL base confirmada na documentação pública do Melhor Envio.
   *
   * @return string
   *   Texto.
   */
  public function baseUrl(): string {
    return match ($this) {
      self::Sandbox => 'https://sandbox.melhorenvio.com.br',
      self::Production => 'https://melhorenvio.com.br',
    };
  }

}
