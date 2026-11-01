<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\EventSubscriber;

use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Soma a checagem de estorno de doação à rota de operação de pagamento do Commerce.
 */
final class DonationRefundRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.commerce_payment.operation_form')) {
      $route->setRequirement('_custom_access', 'aculta_donation.refund_access::access');
    }
  }

}
