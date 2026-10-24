<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Form\PortalFormCallbacks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects one address entity and two independent editing destinations.
 */
#[CoversClass(PortalFormCallbacks::class)]
#[Group('aculta_portal')]
final class PortalAddressStudioContextTest extends UnitTestCase {

  /**
   * The existing account address redirects to its unchanged legacy page.
   */
  public function testLegacyAddressDestination(): void {
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect')
      ->with('aculta_portal.my_data_address');

    $form = [];
    $this->callbacks('aculta_portal.my_data_address')
      ->addressRedirect($form, $state);
  }

  /**
   * Studio address returns to Studio, without changing its owner entity.
   */
  public function testStudioAddressDestination(): void {
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect')
      ->with('aculta_studio.account_address');

    $form = [];
    $this->callbacks('aculta_studio.account_address')
      ->addressRedirect($form, $state);
  }

  /**
   * Unrelated contexts cannot inject arbitrary redirect destinations.
   */
  public function testUnrelatedRouteFallsBackToOriginalAccount(): void {
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect')
      ->with('aculta_portal.my_data_address');

    $form = [];
    $this->callbacks('aculta_studio.profile')
      ->addressRedirect($form, $state);
  }

  /**
   * Isolates the route-dependent callback without initializing mail/auth.
   */
  private function callbacks(string $routeName): PortalFormCallbacks {
    $match = $this->createMock(RouteMatchInterface::class);
    $match->method('getRouteName')->willReturn($routeName);

    $reflection = new \ReflectionClass(PortalFormCallbacks::class);
    $callbacks = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('routeMatch')->setValue($callbacks, $match);
    return $callbacks;
  }

}
