<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

/**
 * Validates provider-owned checkout destinations before external redirects.
 */
final class MercadoPagoRedirectUrl {

  /**
   * Checks HTTPS URLs hosted on official Mercado Pago checkout domains.
   */
  public static function isValid(mixed $url): bool {
    if (!is_string($url) || trim($url) === '') {
      return FALSE;
    }
    $parts = parse_url(trim($url));
    if (!is_array($parts)
      || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
      || isset($parts['user'])
      || isset($parts['pass'])
      || (isset($parts['port']) && $parts['port'] !== 443)) {
      return FALSE;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    return self::hostMatches($host, 'mercadopago.com')
      || self::hostMatches($host, 'mercadopago.com.br')
      || self::hostMatches($host, 'mercadopago.com.ar');
  }

  /**
   * Checks a host boundary without accepting lookalike suffixes.
   */
  private static function hostMatches(string $host, string $domain): bool {
    return $host === $domain || str_ends_with($host, '.' . $domain);
  }

}
