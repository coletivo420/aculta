<?php

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\EventSubscriber\CartCheckoutRouteSubscriber;
use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Test the route changes for /cart and /checkout.
 */
class CartCheckoutSubscriberTest extends UnitTestCase {

  /**
   * Stub config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cartDeniedFactory;

  /**
   * Stub config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cartOnlyFactory;

  /**
   * Stub config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $bothFactory;

  /**
   * Routes under test.
   *
   * @var \Symfony\Component\Routing\RouteCollection
   */
  protected RouteCollection $routeCollection;

  /**
   * Event to trigger the code under test.
   *
   * @var \Drupal\Core\Routing\RouteBuildEvent
   */
  protected RouteBuildEvent $event;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cartDeniedFactory = $this->getConfigFactoryStub([
      'aculta_donation.settings' => [
        'donation_route' => DonationSettingsForm::ROTA_DONATE,
      ],
    ]);
    $this->cartOnlyFactory = $this->getConfigFactoryStub([
      'aculta_donation.settings' => [
        'donation_route' => DonationSettingsForm::ROTA_CART,
      ],
    ]);
    $this->bothFactory = $this->getConfigFactoryStub([
      'aculta_donation.settings' => [
        'donation_route' => DonationSettingsForm::ROTA_AMBAS,
      ],
    ]);

    $this->routeCollection = new RouteCollection();
    $cartRoute = new Route('/cart', [], ['_access' => 'TRUE']);
    $this->routeCollection->add('commerce_cart.page', $cartRoute);
    $checkoutRoute = new Route('/checkout', [], ['_permission' => 'access checkout']);
    $this->routeCollection->add('commerce_checkout.checkout', $checkoutRoute);
    $checkoutFormRoute = new Route('/checkout/{commerce_order}/{step}', [], ['_custom_access' => '\Drupal\commerce_checkout\Controller\CheckoutController::checkAccess']);
    $this->routeCollection->add('commerce_checkout.form', $checkoutFormRoute);
    $this->event = new RouteBuildEvent($this->routeCollection);

  }

  /**
   * Test /cart denied.
   */
  public function testCartDenied() {
    $routeSubscriber = new CartCheckoutRouteSubscriber($this->cartDeniedFactory);
    $routeSubscriber->onAlterRoutes($this->event);
    $route = $this->routeCollection->get('commerce_cart.page');
    $this->assertEquals('FALSE', $route->getRequirement('_access'));
    $route = $this->routeCollection->get('commerce_checkout.checkout');
    $this->assertEquals('FALSE', $route->getRequirement('_access'));
    $this->assertNull($route->getRequirement('_permission'));
    $route = $this->routeCollection->get('commerce_checkout.form');
    $this->assertEquals('FALSE', $route->getRequirement('_access'));
    $this->assertNull($route->getRequirement('_permission'));
  }

  /**
   * Test /cart route (unchanged).
   */
  public function testCartIsSet() {
    $routeSubscriber = new CartCheckoutRouteSubscriber($this->cartOnlyFactory);
    $routeSubscriber->onAlterRoutes($this->event);
    $route = $this->routeCollection->get('commerce_cart.page');
    $this->assertEquals('TRUE', $route->getRequirement('_access'));
    $route = $this->routeCollection->get('commerce_checkout.checkout');
    $this->assertNull($route->getRequirement('_access'));
    $this->assertNotNull($route->getRequirement('_permission'));
    $route = $this->routeCollection->get('commerce_checkout.form');
    $this->assertNull($route->getRequirement('_access'));
    $this->assertNotNull($route->getRequirement('_custom_access'));
  }

  /**
   * Test /cart route (unchanged).
   */
  public function testBothIsSet() {
    $routeSubscriber = new CartCheckoutRouteSubscriber($this->bothFactory);
    $routeSubscriber->onAlterRoutes($this->event);
    $route = $this->routeCollection->get('commerce_cart.page');
    $this->assertEquals('TRUE', $route->getRequirement('_access'));
    $route = $this->routeCollection->get('commerce_checkout.checkout');
    $this->assertNull($route->getRequirement('_access'));
    $this->assertNotNull($route->getRequirement('_permission'));
    $route = $this->routeCollection->get('commerce_checkout.form');
    $this->assertNull($route->getRequirement('_access'));
    $this->assertNotNull($route->getRequirement('_custom_access'));
  }

}

/*
$mockConfig->expects($this->any())
->method('get')
->with('donation_route')
->willReturn(DonationSettingsForm::ROTA_CART);
 */
