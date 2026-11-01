<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Identity\LegacyAuthorDisplayName;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the Growroom suffix is a Forum display rule only.
 */
#[Group('aculta_user')]
final class LegacyAuthorDisplayNameTest extends UnitTestCase {

  /**
   * Appends the source marker to historical names in Forum content.
   */
  public function testForumDisplayNameHasGrowroomSuffix(): void {
    self::assertSame('Pessoa histórica-gr', LegacyAuthorDisplayName::forForum('Pessoa histórica'));
  }

}
