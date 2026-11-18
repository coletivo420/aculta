<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\aculta420_v2\Hook\PortalUiHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the Studio saved-content site-key presentation token.
 */
#[CoversMethod(PortalUiHooks::class, 'preprocessVariables')]
#[Group('aculta420_v2')]
final class PortalUiSiteKeyTokenTest extends UnitTestCase {

  /**
   * Confirms the site-key token keeps the existing badge presentation.
   */
  public function testSavedSiteKeyUsesExistingBadgeStyles(): void {
    $variables = [
      'attributes' => ['data-portal-ui' => 'studio-saved-site-key'],
    ];

    PortalUiHooks::preprocessVariables($variables);

    self::assertInstanceOf(Attribute::class, $variables['attributes']);
    self::assertTrue($variables['attributes']->hasClass('badge'));
    self::assertTrue($variables['attributes']->hasClass('badge-purpose'));
  }

}
