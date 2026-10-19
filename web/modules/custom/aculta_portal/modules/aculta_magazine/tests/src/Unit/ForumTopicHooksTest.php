<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Hook\ForumTopicHooks;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the Magazine boundary from recreating public legacy topics.
 */
#[Group('aculta_magazine')]
final class ForumTopicHooksTest extends UnitTestCase {

  /**
   * Publication lifecycle hooks must not synchronize legacy Forum topics.
   */
  public function testOnlyLegacyCommentLinkHookRemains(): void {
    $hooks = [];
    foreach ((new \ReflectionClass(ForumTopicHooks::class))->getMethods() as $method) {
      foreach ($method->getAttributes(Hook::class) as $attribute) {
        $hooks[] = $attribute->newInstance()->hook;
      }
    }

    self::assertSame(['comment_links_alter'], $hooks);
  }

}
