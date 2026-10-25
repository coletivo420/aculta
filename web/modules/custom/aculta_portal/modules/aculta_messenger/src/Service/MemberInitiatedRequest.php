<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Identifies member-initiated requests that may update notice read state.
 */
final class MemberInitiatedRequest {

  /**
   * Whether a request is a page navigation or same-origin Fetch.
   *
   * Embedded subresources cannot update conversation read state.
   */
  public static function isMemberInitiated(?Request $request): bool {
    if ($request === NULL) {
      return FALSE;
    }

    $site = strtolower(trim((string) $request->headers->get('Sec-Fetch-Site', '')));
    $destination = strtolower(trim((string) $request->headers->get('Sec-Fetch-Dest', '')));
    if ($site === 'cross-site'
      || ($destination !== '' && !in_array($destination, ['document', 'empty'], TRUE))) {
      return FALSE;
    }
    return !self::isPrefetchOrPrerender($request);
  }

  /**
   * Whether a request explicitly identifies itself as prefetch or prerender.
   */
  public static function isPrefetchOrPrerender(?Request $request): bool {
    if ($request === NULL) {
      return TRUE;
    }
    foreach (['Sec-Purpose', 'Purpose'] as $header) {
      $purpose = strtolower(trim((string) $request->headers->get($header, '')));
      if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
