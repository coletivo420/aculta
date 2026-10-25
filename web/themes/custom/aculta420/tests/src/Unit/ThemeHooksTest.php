<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\ThemeHooks;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ACULTA420 theme attachment behavior for shared Messenger components.
 */
#[CoversClass(ThemeHooks::class)]
#[Group('aculta420')]
final class ThemeHooksTest extends UnitTestCase {

  /**
   * Notice grouping styles are declared by the theme metadata, not by a hook.
   *
   * The theme may only register preprocess hooks, so the library is loaded
   * through the theme's libraries list.
   */
  public function testMessengerNoticeStylesAreDeclaredByTheme(): void {
    $info = Yaml::decode((string) file_get_contents(dirname(__DIR__, 3) . '/aculta420.info.yml'));

    self::assertContains('aculta420/messenger-notices', $info['libraries']);
    self::assertFalse(method_exists(ThemeHooks::class, 'attachMessengerNoticeStyles'));
  }

}
