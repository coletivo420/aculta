<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\commerce_checkout\Entity\CheckoutFlowInterface as CheckoutFlowEntityInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Resolver\ChainCheckoutFlowResolverInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\aculta_portal\Checkout\CheckoutStepResolver;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regressions for checkout steps that change visibility after login.
 */
#[Group('aculta_portal')]
final class CheckoutStepResolverTest extends UnitTestCase {

  /**
   * An authenticated checkout skips a stale, now invisible login step.
   */
  public function testAuthenticatedCustomerStartsAtFirstVisibleStep(): void {
    $visible_steps = [
      'order_information' => ['label' => 'Order information'],
      'review' => ['label' => 'Review'],
    ];

    self::assertSame('order_information', CheckoutStepResolver::selectVisibleStep('login', $visible_steps));
  }

  /**
   * An anonymous checkout retains the visible identity step.
   */
  public function testAnonymousCustomerKeepsVisibleLoginStep(): void {
    $visible_steps = [
      'login' => ['label' => 'Log in'],
      'order_information' => ['label' => 'Order information'],
    ];

    self::assertSame('login', CheckoutStepResolver::selectVisibleStep('login', $visible_steps));
  }

  /**
   * The resolver reads visible steps from the active Commerce flow.
   */
  public function testResolveSkipsStoredLoginStepWhenFlowHidesIt(): void {
    $order = $this->createMock(OrderInterface::class);
    $step_field = $this->createMock(FieldItemListInterface::class);
    $step_field->method('__get')->with('value')->willReturn('login');
    $flow_field = $this->createMock(FieldItemListInterface::class);
    $flow_field->method('__get')->with('entity')->willReturn(NULL);
    $order->method('get')->willReturnCallback(
      static fn (string $field): FieldItemListInterface => $field === 'checkout_flow' ? $flow_field : $step_field,
    );
    $order->expects(self::never())->method('save');

    $plugin = $this->createMock(CheckoutFlowInterface::class);
    $plugin->expects(self::once())->method('setOrder')->with($order)->willReturnSelf();
    $plugin->method('getVisibleSteps')->willReturn([
      'order_information' => ['label' => 'Order information'],
      'review' => ['label' => 'Review'],
    ]);
    $flow = $this->createMock(CheckoutFlowEntityInterface::class);
    $flow->method('getPlugin')->willReturn($plugin);
    $resolver = $this->createMock(ChainCheckoutFlowResolverInterface::class);
    $resolver->expects(self::once())->method('resolve')->with($order)->willReturn($flow);

    self::assertSame('order_information', (new CheckoutStepResolver($resolver))->resolve($order));
  }

  /**
   * An empty flow returns no step.
   */
  public function testEmptyFlowHasNoStep(): void {
    self::assertNull(CheckoutStepResolver::selectVisibleStep('login', []));
  }

}
