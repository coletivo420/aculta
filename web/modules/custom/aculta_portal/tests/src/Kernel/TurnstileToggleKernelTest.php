<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Captcha\TurnstileToggle;
use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Turnstile toggle and permanent Commerce purchase exceptions.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class TurnstileToggleKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'key', 'captcha'];

  /**
   * The toggle under test.
   */
  private TurnstileToggle $toggle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['captcha']);
    // Captcha includes standard points. Adjust them without duplicating.
    $this->point('user_login_form', TurnstileToggle::CHALLENGE);
    $this->point('user_register_form', TurnstileToggle::CHALLENGE);
    $this->point('commerce_order_item_add_to_cart_form_commerce_product_42', TurnstileToggle::CHALLENGE);
    $this->point('commerce_checkout_flow_shipping', TurnstileToggle::CHALLENGE);
    $this->point('user_pass', 'captcha/Math');
    $this->toggle = new TurnstileToggle(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('key.repository'),
      $this->container->get('logger.factory'),
    );
  }

  /**
   * Creates or updates an active CAPTCHA point for the test.
   */
  private function point(string $formId, string $type): void {
    $point = CaptchaPoint::load($formId) ?? CaptchaPoint::create(['formId' => $formId]);
    $point->set('captchaType', $type);
    $point->setStatus(TRUE);
    $point->save();
  }

  /**
   * Tests disabling only Turnstile points.
   */
  public function testDisableTurnsOffOnlyTurnstilePoints(): void {
    $this->toggle->setEnabled(FALSE, 'teste');
    $this->assertFalse($this->toggle->isEnabled());
    $this->assertFalse((bool) $this->config('captcha.settings')->get('enable_globally'));
    $this->assertFalse((bool) CaptchaPoint::load('user_login_form')->status());
    $this->assertFalse((bool) CaptchaPoint::load('user_register_form')->status());
    $this->assertTrue((bool) CaptchaPoint::load('user_pass')->status(), 'Desafio de outro tipo não muda.');
  }

  /**
   * Tests re-enabling Turnstile while purchase points stay disabled.
   */
  public function testEnableRestoresTurnstilePoints(): void {
    $this->toggle->setEnabled(FALSE, 'teste');
    $this->toggle->setEnabled(TRUE, 'teste');
    $this->assertTrue($this->toggle->isEnabled());
    $this->assertTrue((bool) $this->config('captcha.settings')->get('enable_globally'));
    $this->assertTrue((bool) CaptchaPoint::load('user_login_form')->status());
    $product_point = CaptchaPoint::load('commerce_order_item_add_to_cart_form_commerce_product_42');
    $this->assertFalse((bool) $product_point->status());
    $this->assertFalse((bool) CaptchaPoint::load('commerce_checkout_flow_shipping')->status());
  }

  /**
   * Tests that no secret value is required to report key status.
   */
  public function testKeyIsNotConfiguredWithoutSecretValue(): void {
    $this->assertFalse($this->toggle->keyConfigured());
  }

}
