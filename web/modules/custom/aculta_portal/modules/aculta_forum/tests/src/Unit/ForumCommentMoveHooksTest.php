<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumCommentMoveHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests dependency wiring for the Forum comment move operation.
 */
#[Group('aculta_forum')]
final class ForumCommentMoveHooksTest extends UnitTestCase {

  /**
   * The hook injects the named mover service by its registered ID.
   */
  public function testMoverArgumentUsesTheRegisteredService(): void {
    $parameter = (new \ReflectionMethod(ForumCommentMoveHooks::class, '__construct'))
      ->getParameters()[0];
    $attributes = $parameter->getAttributes(Autowire::class);

    self::assertCount(1, $attributes);
    $reference = $attributes[0]->newInstance()->value;
    self::assertInstanceOf(Reference::class, $reference);
    self::assertSame('aculta_forum.comment_mover', $reference->__toString());
  }

}
