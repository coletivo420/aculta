<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumDiscussionWriteHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the public Forum write-guard hook integration.
 */
#[Group('aculta_forum')]
final class ForumDiscussionWriteHooksTest extends UnitTestCase {

  /**
   * The OOP hooks inject the shared discussion write guard.
   */
  public function testGuardUsesItsRegisteredService(): void {
    $parameter = (new \ReflectionMethod(ForumDiscussionWriteHooks::class, '__construct'))
      ->getParameters()[0];
    $attributes = $parameter->getAttributes(Autowire::class);

    self::assertCount(1, $attributes);
    $reference = $attributes[0]->newInstance()->value;
    self::assertInstanceOf(Reference::class, $reference);
    self::assertSame('aculta_forum.discussion_write_guard', $reference->__toString());
  }

}
