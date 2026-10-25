<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\PortalUiHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that neutral component requests from the Portal map to theme SDCs.
 */
#[CoversClass(PortalUiHooks::class)]
#[Group('aculta420')]
final class PortalComponentPreprocessTest extends UnitTestCase {

  /**
   * An allowed neutral name renders the matching ACULTA420 SDC with its props.
   */
  public function testAllowedNameRendersThemeComponent(): void {
    $variables = [
      'name' => 'card',
      'props' => ['title' => 'Título'],
      'slots' => ['body' => ['#markup' => 'corpo']],
    ];

    (new PortalUiHooks())->preprocessPortalComponent($variables);

    self::assertSame('component', $variables['component']['#type']);
    self::assertSame('aculta420:card', $variables['component']['#component']);
    self::assertSame(['title' => 'Título'], $variables['component']['#props']);
    self::assertSame(['body' => ['#markup' => 'corpo']], $variables['component']['#slots']);
  }

  /**
   * An unknown name renders nothing, so the module cannot pick a component id.
   */
  public function testUnknownNameRendersNothing(): void {
    $variables = ['name' => 'aculta420:anything', 'props' => [], 'slots' => []];

    (new PortalUiHooks())->preprocessPortalComponent($variables);

    self::assertSame([], $variables['component']);
  }

  /**
   * A missing name renders nothing.
   */
  public function testMissingNameRendersNothing(): void {
    $variables = [];

    (new PortalUiHooks())->preprocessPortalComponent($variables);

    self::assertSame([], $variables['component']);
  }

}
