<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\EventSubscriber;

use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class CartCheckoutRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a CartCheckoutRouteSubscriber object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // @see https://www.drupal.org/node/2187643
    $config = $this->configFactory->get('aculta_donation.settings');
    // Calculate access.
    $use_route = $config->get('donation_route');
    $route_check = $use_route === DonationSettingsForm::ROTA_CART || $use_route === DonationSettingsForm::ROTA_AMBAS;
    $denyAccessString = 'FALSE';
    $cartRoute = $collection->get('commerce_cart.page');
    $checkoutController = $collection->get('commerce_checkout.checkout');
    $checkoutForm = $collection->get('commerce_checkout.form');
    if ($cartRoute instanceof Route && !$route_check) {
      $cartRoute->setRequirement('_access', $denyAccessString);
    }
    if ($checkoutController instanceof Route && !$route_check) {
      $requirements = $checkoutController->getRequirements();
      unset($requirements['_permission']);
      $requirements['_access'] = $denyAccessString;
      $checkoutController->setRequirements($requirements);
    }
    if ($checkoutForm instanceof Route && !$route_check) {
      $requirements = $checkoutForm->getRequirements();
      unset($requirements['_permission']);
      $requirements['_access'] = $denyAccessString;
      $checkoutForm->setRequirements($requirements);
    }
  }

}
