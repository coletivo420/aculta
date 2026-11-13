<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
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

  public function testDoesNotAttachForPublishedEntity(): void {
    $settings = $this->createMock(ThemeSettingsProvider::class);
    $settings->expects(self::never())->method('getSetting');
    $entity = $this->createMock(EntityPublishedInterface::class);
    $entity->method('isPublished')->willReturn(TRUE);
    $build = [];

    (new UnpublishedPreviewHooks($settings))->entityView(
      $build,
      $entity,
      $this->createMock(EntityViewDisplayInterface::class),
      'full',
    );

    self::assertArrayNotHasKey('#attached', $build);
  }

  public function testAttachesOnlyWhenUiStylesSelectedTheMarker(): void {
    $settings = $this->createMock(ThemeSettingsProvider::class);
    $settings->expects(self::once())
      ->method('getSetting')
      ->with(UiStylesEntityStatusInterface::UNPUBLISHED_CLASSES_THEME_SETTING_KEY)
      ->willReturn(['selected' => ['aculta-content-status--unpublished']]);
    $entity = $this->createMock(EntityPublishedInterface::class);
    $entity->method('isPublished')->willReturn(FALSE);
    $build = [];

    (new UnpublishedPreviewHooks($settings))->entityView(
      $build,
      $entity,
      $this->createMock(EntityViewDisplayInterface::class),
      'full',
    );

    self::assertSame(['aculta420_v2/unpublished-preview'], $build['#attached']['library']);
  }

  public function testDoesNotAttachWhenUiStylesDidNotSelectTheMarker(): void {
    $settings = $this->createMock(ThemeSettingsProvider::class);
    $settings->method('getSetting')->willReturn(['selected' => ['other-editorial-marker']]);
    $entity = $this->createMock(EntityPublishedInterface::class);
    $entity->method('isPublished')->willReturn(FALSE);
    $build = [];

    (new UnpublishedPreviewHooks($settings))->entityView(
      $build,
      $entity,
      $this->createMock(EntityViewDisplayInterface::class),
      'full',
    );

    self::assertArrayNotHasKey('#attached', $build);
  }

}
