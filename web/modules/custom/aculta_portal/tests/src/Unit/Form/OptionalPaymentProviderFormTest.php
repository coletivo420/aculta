<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Form;

use Drupal\aculta_portal\Form\SalesPaymentCancelForm;
use Drupal\aculta_portal\Form\SalesPaymentRefreshForm;
use Drupal\aculta_portal\Support\Form\SubscriptionActionForm;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verifies that payment action forms disappear when no provider is installed.
 */
#[Group('aculta_portal')]
final class OptionalPaymentProviderFormTest extends UnitTestCase {

  /**
   * Tests cancellation is unavailable without a payment provider.
   */
  public function testCancelFormDeniesAccessWithoutProvider(): void {
    $form = new SalesPaymentCancelForm(
      NULL,
      $this->createMock(AccountInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $form->buildForm([], $this->createMock(FormStateInterface::class));
  }

  /**
   * Tests refresh is unavailable without a payment provider.
   */
  public function testRefreshFormDeniesAccessWithoutProvider(): void {
    $form = new SalesPaymentRefreshForm(
      NULL,
      $this->createMock(AccountInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $form->buildForm([], $this->createMock(FormStateInterface::class));
  }

  /**
   * Tests subscription actions are unavailable without a payment provider.
   */
  public function testSubscriptionActionRejectsWhenProviderIsMissing(): void {
    $order = $this->createMock(OrderInterface::class);
    $order->method('getCustomerId')->willReturn('42');
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $form = new SubscriptionActionForm(
      NULL,
      $this->createMock(EntityTypeManagerInterface::class),
      $account,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $form->buildForm([], $this->createMock(FormStateInterface::class), $order, 'pausar');
  }

}
