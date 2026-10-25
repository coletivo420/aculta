<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Identifies member-initiated requests that may update notice read state.
 */
final class MemberInitiatedRequest {

  /**
   * Whether a request is a same-site navigation rather than prefetch/prerender.
   */
  public static function isMemberInitiated(?Request $request): bool {
    if ($request === NULL || $request->headers->get('Sec-Fetch-Site') === 'cross-site') {
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
    $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose') ?? ''));
    return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
  }

}
