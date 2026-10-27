<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Controller;

use Drupal\aculta_commerce_melhorenvio\Controller\ShippingSimulatorController;
use Drupal\aculta_commerce_melhorenvio\Quote\CartQuoteLines;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks that the simulator hands original CEP input to the validating store.
 */
#[CoversClass(ShippingSimulatorController::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingSimulatorControllerTest extends UnitTestCase {

  /**
   * A formatted CEP is passed unchanged for normalization and persistence.
   */
  public function testFormattedPostalCodeIsPassedToTheSharedStore(): void {
    $result = ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_ORIGIN);
    $store = $this->createMock(ShippingQuoteStore::class);
    $store->expects(self::once())->method('save')->with('01310-100', $result);
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->expects(self::once())->method('simulateForCartLines')->with('01310-100', [])->willReturn($result);

    $response = $this->controller($simulator, $store)->simulate(Request::create('/frete/simular?cep=01310-100'));

    self::assertSame(200, $response->getStatusCode());
  }

  /**
   * Malformed text is not stripped into a valid-looking CEP before validation.
   */
  public function testMalformedPostalCodeIsPassedUnchangedForRejection(): void {
    $input = 'abc01310100';
    $result = ShippingSimulation::unavailable(ShippingSimulation::REASON_INVALID_POSTAL_CODE);
    $store = $this->createMock(ShippingQuoteStore::class);
    $store->expects(self::once())->method('save')->with($input, $result);
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->expects(self::once())->method('simulateForCartLines')->with($input, [])->willReturn($result);

    $this->controller($simulator, $store)->simulate(Request::create('/frete/simular?cep=' . $input));
  }

  /**
   * Creates a controller with flood control enabled and no cart lines.
   */
  private function controller(ShippingSimulator $simulator, ShippingQuoteStore $store): ShippingSimulatorController {
    $flood = $this->createMock(FloodInterface::class);
    $flood->method('isAllowed')->willReturn(TRUE);
    $flood->expects(self::once())->method('register');
    $cartLines = $this->createMock(CartQuoteLines::class);
    $cartLines->method('lines')->willReturn([]);

    return new ShippingSimulatorController(
      $simulator,
      $flood,
      $store,
      $this->createMock(EntityTypeManagerInterface::class),
      $cartLines,
    );
  }

}
