<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ui_library\Unit;

use Drupal\aculta_ui_library\Controller\UiPatternsLibraryRedirectController;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tests the compatibility redirect to the canonical SDC catalog.
 *
 * @coversDefaultClass \Drupal\aculta_ui_library\Controller\UiPatternsLibraryRedirectController
 * @group aculta_ui_library
 */
final class UiPatternsLibraryRedirectControllerTest extends UnitTestCase {

  /**
   * The old ACULTA library entry point redirects to UI Patterns Library.
   *
   * @covers ::redirectToLibrary
   */
  public function testRedirectsToUiPatternsLibrary(): void {
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->expects(self::once())
      ->method('generate')
      ->with('ui_patterns_library.overview', [], UrlGeneratorInterface::ABSOLUTE_PATH)
      ->willReturn('/admin/structure/ui-patterns');

    $response = (new UiPatternsLibraryRedirectController($urlGenerator))
      ->redirectToLibrary();

    self::assertSame(302, $response->getStatusCode());
    self::assertSame('/admin/structure/ui-patterns', $response->getTargetUrl());
  }

}
