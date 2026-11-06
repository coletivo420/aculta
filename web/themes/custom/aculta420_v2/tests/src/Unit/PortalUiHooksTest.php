<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Hook\PortalUiHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests presentation token application to rendered Drupal containers.
 */
#[CoversClass(PortalUiHooks::class)]
#[Group('aculta420_v2')]
final class PortalUiHooksTest extends UnitTestCase {

  /**
   * Applies the mapped Studio layout classes after Core builds container attrs.
   */
  public function testContainerPreprocessPreservesMappedAttributeTokens(): void {
    $variables = ['attributes' => ['data-portal-ui' => 'studio-profile-editor']];

    (new PortalUiHooks())->preprocessContainer($variables);

    self::assertInstanceOf(Attribute::class, $variables['attributes']);
    self::assertSame('studio-profile-editor', (string) $variables['attributes']['data-portal-ui']->value());
    self::assertTrue($variables['attributes']->hasClass('aculta-profile-editor'));
  }

  /**
   * Maps array attributes in generic preprocess variables as well.
   */
  public function testGenericPreprocessConvertsAndMapsAttributeArrays(): void {
    $variables = ['wrapper_attributes' => ['data-portal-ui' => 'studio-profile-editor']];

    (new PortalUiHooks())->preprocess($variables);

    self::assertInstanceOf(Attribute::class, $variables['wrapper_attributes']);
    self::assertTrue($variables['wrapper_attributes']->hasClass('aculta-profile-editor'));
  }

}
