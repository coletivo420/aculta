<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Hook\UnpublishedPreviewHooks;
use Drupal\ui_styles_entity_status\UiStylesEntityStatusInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies conditional loading of unpublished editorial preview styling.
 */
#[CoversClass(UnpublishedPreviewHooks::class)]
#[Group('aculta420_v2')]
final class UnpublishedPreviewHooksTest extends UnitTestCase {

  /**
   * Provides the testDoesNotAttachForPublishedEntity operation.
   */
  public function testDoesNotAttachForPublishedEntity(): void {
    $settings = $this->createMock(ThemeSettingsProvider::class);
    $settings->expects(self::never())->method('getSetting');
    $node = $this->createMock(NodeInterface::class);
    $node->method('isPublished')->willReturn(TRUE);
    $variables = ['node' => $node, 'elements' => []];

    (new UnpublishedPreviewHooks($settings))->preprocessNode($variables);

    self::assertIsArray($variables['elements']);
    self::assertArrayNotHasKey('#attached', $variables['elements']);
  }

  /**
   * Provides the testAttachesOnlyWhenUiStylesSelectedTheMarker operation.
   */
  public function testAttachesOnlyWhenUiStylesSelectedTheMarker(): void {
    $settings = $this->createMock(ThemeSettingsProvider::class);
    $settings->expects(self::once())
      ->method('getSetting')
      ->with(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY)
      ->willReturn(['selected' => ['aculta-content-status--unpublished']]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('isPublished')->willReturn(FALSE);
    $variables = ['node' => $node, 'elements' => []];

    (new UnpublishedPreviewHooks($settings))->preprocessNode($variables);

    self::assertIsArray($variables['elements']);
    self::assertIsArray($variables['elements']['#attached'] ?? NULL);
    self::assertSame(
      ['aculta420_v2/unpublished-preview'],
      $variables['elements']['#attached']['library'],
    );
  }

  /**
   * Provides the testDoesNotAttachWhenUiStylesDidNotSelectTheMarker operation.
   */
  public function testDoesNotAttachWhenUiStylesDidNotSelectTheMarker(): void {
    $settings = $this->createMock(ThemeSettingsProvider::class);
    $settings->method('getSetting')->willReturn(['selected' => ['other-editorial-marker']]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('isPublished')->willReturn(FALSE);
    $variables = ['node' => $node, 'elements' => []];

    (new UnpublishedPreviewHooks($settings))->preprocessNode($variables);

    self::assertIsArray($variables['elements']);
    self::assertArrayNotHasKey('#attached', $variables['elements']);
  }

}
