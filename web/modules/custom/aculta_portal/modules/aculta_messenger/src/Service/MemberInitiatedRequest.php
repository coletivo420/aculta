<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Identifies member-initiated requests that may update notice read state.
 */
final class MemberInitiatedRequest {

  /**
   * Whether a request is an activated document navigation or same-origin Fetch.
   *
   * Embedded subresources cannot update conversation read state.
   */
  public static function isMemberInitiated(?Request $request): bool {
    if ($request === NULL) {
      return FALSE;
    }

    $site = strtolower(trim((string) $request->headers->get('Sec-Fetch-Site', '')));
    $destination = strtolower(trim((string) $request->headers->get('Sec-Fetch-Dest', '')));
    $requestedWith = strtolower(trim((string) $request->headers->get('X-Requested-With', '')));
    $userActivation = trim((string) $request->headers->get('Sec-Fetch-User', ''));
    if ($site === 'cross-site'
      || ($destination !== '' && !in_array($destination, ['document', 'empty'], TRUE))) {
      return FALSE;
    }
    if (self::isPrefetchOrPrerender($request)) {
      return FALSE;
    }

    $drawerFetch = $requestedWith === 'xmlhttprequest'
      && ($destination === '' || $destination === 'empty')
      && ($site === '' || $site === 'same-origin');
    $activatedNavigation = $userActivation === '?1'
      && ($destination === '' || $destination === 'document');
    return $drawerFetch || $activatedNavigation;
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
