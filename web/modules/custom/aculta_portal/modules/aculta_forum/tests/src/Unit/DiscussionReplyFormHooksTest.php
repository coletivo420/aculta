<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Utility\CallableResolver;
use Drupal\aculta_forum\Hook\DiscussionReplyFormHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies dependency-injected Form API callbacks resolve by service ID.
 */
#[Group('aculta_forum')]
final class DiscussionReplyFormHooksTest extends UnitTestCase {

  /**
   * Form callbacks are service definitions and do not serialize service objects.
   */
  public function testFormCallbacksResolveThroughServiceNotation(): void {
    $hook = (new \ReflectionClass(DiscussionReplyFormHooks::class))->newInstanceWithoutConstructor();
    $classResolver = $this->createMock(ClassResolverInterface::class);
    $classResolver->expects(self::exactly(3))
      ->method('getInstanceFromDefinition')
      ->with(DiscussionReplyFormHooks::class)
      ->willReturn($hook);
    $resolver = new CallableResolver($classResolver);

    foreach (['buildCommentEntityWithPid', 'validateIdempotency', 'postSubmitRedirect'] as $method) {
      $callback = $resolver->getCallableFromDefinition(DiscussionReplyFormHooks::class . ':' . $method);
      self::assertSame([$hook, $method], $callback);
    }
  }

}
