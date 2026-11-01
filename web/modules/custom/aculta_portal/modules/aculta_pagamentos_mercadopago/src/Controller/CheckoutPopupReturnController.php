<?php

namespace Drupal\aculta_pagamentos_mercadopago\Controller;

use Drupal\aculta_portal\Integration\PopupReturnPage;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the page that closes the checkout popup and redirects the opener.
 *
 * Used when Checkout Pro is opened in a popup (external redirect). Mercado Pago
 * redirects here after payment; this page closes the popup and sends the
 * parent window to the Commerce return URL so only one tab remains. The page
 * itself is the shared integration return page (PopupReturnPage).
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
    // Allow only same-origin or relative URLs to avoid open redirect.
    if ($redirect === '' || !PopupReturnPage::isAllowedTarget($redirect, $request)) {
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
    return PopupReturnPage::response($request, $redirect);
  }

}
