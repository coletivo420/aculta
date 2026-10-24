<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

/**
 * Filters the shared notice render list by source and read state.
 */
final class NoticeListFilter {

  public const ORIGINS = ['comunicado', 'forum', 'alerta', 'aprendizagem', 'wiki', 'loja', 'doacao', 'conta'];

  public const READ_STATES = ['read', 'unread'];

  /**
   * Normalizes untrusted query parameters to supported filters.
   */
  public function normalize(array $filters): array {
    $origin = $filters['origem'] ?? '';
    $read = $filters['leitura'] ?? '';
    return [
      'origem' => is_string($origin) && in_array($origin, self::ORIGINS, TRUE) ? $origin : '',
      'leitura' => is_string($read) && in_array($read, self::READ_STATES, TRUE) ? $read : '',
    ];
  }

  /**
   * Adds normalized filters to a local route URL without dropping its query.
   */
  public function appendToUrl(string $url, array $filters): string {
    $filters = array_filter(
      $this->normalize($filters),
      static fn(string $value): bool => $value !== '',
    );
    if ($filters === []) {
      return $url;
    }

    $fragment = '';
    if (($fragmentPosition = strpos($url, '#')) !== FALSE) {
      $fragment = substr($url, $fragmentPosition);
      $url = substr($url, 0, $fragmentPosition);
    }
    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . http_build_query($filters, '', '&', PHP_QUERY_RFC3986) . $fragment;
  }

  /**
   * Filters notice render arrays after all producer adapters have contributed.
   *
   * Untagged items remain visible in the unfiltered list and are omitted only
   * when a source or read-state filter is explicitly active.
   */
  public function apply(array $items, array $filters): array {
    $filters = $this->normalize($filters);
    if ($filters['origem'] === '' && $filters['leitura'] === '') {
      return array_values($items);
    }

    return array_values(array_filter($items, static function (array $item) use ($filters): bool {
      $attributes = $item['#attributes'] ?? [];
      if (!is_array($attributes)) {
        return FALSE;
      }
      if ($filters['origem'] !== '' && ($attributes['data-aculta-notice-origin'] ?? NULL) !== $filters['origem']) {
        return FALSE;
      }
      if ($filters['leitura'] !== '' && ($attributes['data-aculta-notice-read'] ?? NULL) !== $filters['leitura']) {
        return FALSE;
      }
      return TRUE;
    }));
  }

}
