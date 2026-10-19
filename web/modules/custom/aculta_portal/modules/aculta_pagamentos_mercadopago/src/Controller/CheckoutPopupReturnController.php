<?php

namespace Drupal\aculta_pagamentos_mercadopago\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the page that closes the checkout popup and redirects the opener.
 *
 * Used when Checkout Pro is opened in a popup (external redirect). Mercado Pago
 * redirects here after payment; this page closes the popup and sends the
 * parent window to the Commerce return URL so only one tab remains.
 */
final class CheckoutPopupReturnController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Closes the popup and redirects the opener to the final return URL.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request. Must contain query param 'redirect' with the final URL.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Minimal HTML that runs the close-and-redirect script.
   */
  public function returnPage(Request $request) {
    $raw = $request->query->get('redirect');
    $redirect = is_string($raw) ? $raw : '';
    if ($redirect === '') {
      $redirect = '/';
    }
    // Allow only same-origin or relative URLs to avoid open redirect.
    if (!$this->isAllowedRedirect($redirect, $request)) {
      $redirect = '/';
    }
    // Mercado Pago appends payment_id, merchant_order_id, status, etc. to the
    // URL when redirecting. Pass them through to the Commerce return URL so
    // onReturn() can process the payment result.
    $query = $request->query->all();
    unset($query['redirect']);
    if (!empty($query)) {
      $separator = strpos($redirect, '?') !== FALSE ? '&' : '?';
      $redirect = $redirect . $separator . http_build_query($query);
    }
    $redirect_escaped = htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8');
    $redirect_js = $this->jsString($redirect);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Redirecting...</title>
</head>
<body>
<p>Redirecting...</p>
<script>
(function() {
  var redirect = {$redirect_js};
  if (window.opener) {
    window.opener.location.href = redirect;
    window.close();
  } else {
    window.location.href = redirect;
  }
})();
</script>
<noscript><a href="{$redirect_escaped}">Continue</a></noscript>
</body>
</html>
HTML;

    return new Response($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
  }

  /**
   * Encodes a value as a JavaScript string literal safe inside a <script> block.
   *
   * JSON_HEX_* also escapes '<', '>', '&', quotes and apostrophes, so a value
   * such as "</script>" cannot close the script element early.
   */
  private function jsString(string $value): string {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '""';
  }

  /**
   * Checks whether the redirect target is a same-site path or same-origin URL.
   *
   * Browsers treat "//host" and "/\host" as protocol-relative URLs to another
   * host, so a relative path must have a non-slash, non-backslash second
   * character. Schemes such as javascript: never pass.
   */
  private function isAllowedRedirect(string $url, Request $request): bool {
    if ($url === '/' || preg_match('#^/[^/\\\\]#', $url)) {
      return TRUE;
    }
    return preg_match('#^https?://#i', $url) === 1 && $this->isSameOrigin($url, $request);
  }

  /**
   * Checks if the given URL is same-origin with the current request.
   */
  private function isSameOrigin(string $url, Request $request): bool {
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    $scheme = $parsed['scheme'] ?? '';
    return strtolower($scheme) === $request->getScheme() && $host === $request->getHost();
  }

}
