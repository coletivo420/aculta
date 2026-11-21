<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves a safe same-origin return path for non-JS reaction POSTs.
 *
 * Neither the destination query argument nor the Referer header is trusted.
 * The returned URL is always a site-local path (no scheme/authority).
 */
final class ReactionReturnUrl {

  /**
   * Resolves an in-site return URL or safely falls back to the site front.
   */
  public static function resolve(mixed $destination, ?string $referer, Request $request): string {
    foreach ([$destination, $referer] as $candidate) {
      $path = self::localPath($candidate, $request);
      if ($path !== NULL) {
        return $path;
      }
    }

    return '/';
  }

  /**
   * Normalizes an untrusted URL without allowing an external redirect.
   */
  private static function localPath(mixed $candidate, Request $request): ?string {
    if (!is_string($candidate) || $candidate === ''
      || preg_match('/[\x00-\x20\x7F\\\\]/', $candidate) === 1) {
      return NULL;
    }

    if (str_starts_with($candidate, '/')) {
      return str_starts_with($candidate, '//') ? NULL : $candidate;
    }

    $parts = parse_url($candidate);
    if (!is_array($parts)) {
      return NULL;
    }

    // An absolute Referer is valid only when scheme + host + port match.
    $scheme = $parts['scheme'] ?? NULL;
    $host = $parts['host'] ?? NULL;
    if (!is_string($scheme) || !is_string($host)
      || strtolower($scheme) !== strtolower($request->getScheme())
      || strtolower($host) !== strtolower($request->getHost())
      || isset($parts['user']) || isset($parts['pass'])) {
      return NULL;
    }

    $port = $parts['port'] ?? (strtolower($scheme) === 'https' ? 443 : 80);
    if ($port !== $request->getPort()) {
      return NULL;
    }

    $path = $parts['path'] ?? '/';
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }

    return $path
      . (isset($parts['query']) ? '?' . $parts['query'] : '')
      . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
  }

}
