<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Unit;

use Drupal\aculta_forum_legacy\Author\LegacyForumUserDisplayName;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the Growroom suffix is a Forum display rule only.
 */
#[Group('aculta_forum_legacy')]
final class LegacyForumUserDisplayNameTest extends UnitTestCase {

  /**
   * Appends the source marker to historical names in Forum content.
   */
  public function testForumDisplayNameHasGrowroomSuffix(): void {
    self::assertSame('Pessoa histórica-gr', LegacyForumUserDisplayName::forForum('Pessoa histórica'));
  }

}
