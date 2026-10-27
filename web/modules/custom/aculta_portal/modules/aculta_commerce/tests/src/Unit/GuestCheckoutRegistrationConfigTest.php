<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_portal\Hook\CheckoutPaneHooks;
use Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\AcultaCheckoutLogin;
use Drupal\aculta_portal\Hook\ShopCaptchaHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Confirms guest account registration is delegated to Commerce Core.
 */
#[Group('aculta_commerce')]
final class GuestCheckoutRegistrationConfigTest extends UnitTestCase {

  /**
   * Reads a config/sync YAML file.
   *
   * @return array<string, mixed>
   *   Parsed configuration.
   */
  private function config(string $name): array {
    $root = dirname(__DIR__, 9);
    $path = $root . '/config/sync/' . $name . '.yml';
    $this->assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Tests shared store/LMS checkout flows.
   */
  public function testStoreAndLmsFlowsCreateGuestAccounts(): void {
    foreach (['default', 'shipping'] as $id) {
      $configuration = $this->config('commerce_checkout.commerce_checkout_flow.' . $id)['configuration'];
      $this->assertTrue($configuration['guest_new_account'], $id);
      $this->assertTrue($configuration['guest_new_account_notify'], $id);
      $this->assertFalse($configuration['guest_order_assign'], $id);
      $this->assertTrue($configuration['panes']['login']['allow_guest_checkout'], $id);
      $this->assertFalse($configuration['panes']['login']['allow_registration'], $id);
    }
    $course = $this->config('commerce_order.commerce_order_type.curso');
    $this->assertSame('default', $course['third_party_settings']['commerce_checkout']['checkout_flow']);
  }

  /**
   * Tests one-time and recurring donation checkout flows.
   */
  public function testDonationFlowsCreateGuestAccounts(): void {
    foreach ([
      'doacao' => 'doacao',
      'assinatura_flow' => 'assinatura',
    ] as $flow => $order_type) {
      $configuration = $this->config('commerce_checkout.commerce_checkout_flow.' . $flow)['configuration'];
      $this->assertTrue($configuration['guest_new_account'], $flow);
      $this->assertTrue($configuration['guest_new_account_notify'], $flow);
      $this->assertFalse($configuration['guest_order_assign'], $flow);
      $this->assertTrue($configuration['panes']['login']['allow_guest_checkout'], $flow);
      $this->assertFalse($configuration['panes']['login']['allow_registration'], $flow);
      $this->assertSame($flow, $this->config('commerce_order.commerce_order_type.' . $order_type)['third_party_settings']['commerce_checkout']['checkout_flow']);
    }
  }

  /**
   * Tests that Commerce purchase forms are explicit CAPTCHA exceptions.
   */
  public function testCommercePurchaseFormsHaveNoCaptcha(): void {
    foreach ([
      'commerce_order_item_add_to_cart_form',
      'views_form_commerce_cart_form_default',
      'commerce_checkout_flow',
      'commerce_checkout_flow_default',
      'commerce_checkout_flow_fluxo_doacao',
    ] as $form_id) {
      $point = $this->config('captcha.captcha_point.' . $form_id);
      $this->assertFalse($point['status'], $form_id);
    }
  }

  /**
   * Tests that checkout flows use the Portal's email-first login pane.
   */
  public function testCheckoutUsesEmailFirstLoginPane(): void {
    $definitions = [
      'login' => ['class' => 'Drupal\\commerce_checkout\\Plugin\\Commerce\\CheckoutPane\\Login'],
    ];
    (new CheckoutPaneHooks())->commerceCheckoutPaneInfoAlter($definitions);
    $this->assertSame(AcultaCheckoutLogin::class, $definitions['login']['class']);
  }

  /**
   * Tests the global CAPTCHA fallback is removed from cart view forms.
   */
  public function testCartViewFormDropsCaptchaElement(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $hooks = new ShopCaptchaHooks($route_match);
    $form = [
      'captcha' => ['#type' => 'captcha'],
      'nested' => ['captcha' => ['#type' => 'captcha']],
      'submit' => ['#type' => 'submit'],
    ];
    $hooks->formAlter($form, $this->createMock(FormStateInterface::class), 'views_form_commerce_cart_form_default_42');
    $this->assertArrayNotHasKey('captcha', $form);
    $this->assertArrayNotHasKey('captcha', $form['nested']);
    $this->assertArrayHasKey('submit', $form);
  }

}
