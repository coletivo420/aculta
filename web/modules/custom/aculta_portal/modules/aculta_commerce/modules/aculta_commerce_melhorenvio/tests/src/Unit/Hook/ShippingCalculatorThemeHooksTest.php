<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Hook;

use Drupal\aculta_commerce_melhorenvio\Hook\ShippingCalculatorThemeHooks;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests theme hook registration for the shipping calculator.
 */
#[CoversClass(ShippingCalculatorThemeHooks::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingCalculatorThemeHooksTest extends UnitTestCase {

  public function testTemplatePathUsesInjectedModuleList(): void {
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->expects(self::once())
      ->method('getPath')
      ->with('aculta_commerce_melhorenvio')
      ->willReturn('modules/custom/aculta_commerce_melhorenvio');
    $hooks = new ShippingCalculatorThemeHooks($moduleList);

    $theme = $hooks->theme();

    self::assertSame('modules/custom/aculta_commerce_melhorenvio/templates', $theme['aculta_shipping_calculator']['path']);
  }

}
