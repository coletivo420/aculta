<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumTopicMoveHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the dependency wiring of the topic move hook.
 */
#[Group('aculta_forum')]
final class ForumTopicMoveHooksTest extends UnitTestCase {

  /**
   * The hook injects the named mover service by its registered ID.
   */
  public function testMoverArgumentUsesTheRegisteredService(): void {
    $parameter = (new \ReflectionMethod(ForumTopicMoveHooks::class, '__construct'))
      ->getParameters()[0];
    $attributes = $parameter->getAttributes(Autowire::class);

    self::assertCount(1, $attributes);
    $reference = $attributes[0]->newInstance()->value;
    self::assertInstanceOf(Reference::class, $reference);
    self::assertSame('aculta_forum.topic_mover', $reference->__toString());
  }

}
