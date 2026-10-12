<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * /pagamentos foi removido da Minha conta: o pagamento é do Mercado Pago e o portal não exibe meios de pagamento.
 *
 * Links antigos recebem 301 para "Meus pedidos" (/pedidos), em vez de 404. Ver docs/portal/REDIRECTS.md.
 */
final class PaymentMethodsRedirectController {

  public function redirectToOrders(): TrustedRedirectResponse {
    $url = Url::fromRoute('view.commerce_user_orders.order_page')->setAbsolute()->toString();
    return new TrustedRedirectResponse($url, 301);
  }

}
