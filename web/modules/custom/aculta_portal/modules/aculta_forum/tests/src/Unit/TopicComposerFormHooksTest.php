<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Utility\CallableResolver;
use Drupal\aculta_forum\Hook\TopicComposerFormHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the topic composer redirect callback resolves through its service ID.
 */
#[Group('aculta_forum')]
final class TopicComposerFormHooksTest extends UnitTestCase {

  /**
   * Form callbacks use service notation to avoid serializing hook services.
   */
  public function testRedirectCallbackResolvesThroughServiceNotation(): void {
    $hook = (new \ReflectionClass(TopicComposerFormHooks::class))->newInstanceWithoutConstructor();
    $classResolver = $this->createMock(ClassResolverInterface::class);
    $classResolver->expects(self::once())
      ->method('getInstanceFromDefinition')
      ->with(TopicComposerFormHooks::class)
      ->willReturn($hook);
    $resolver = new CallableResolver($classResolver);

    $callback = $resolver->getCallableFromDefinition(TopicComposerFormHooks::class . ':redirectPopupToTopic');

    self::assertSame([$hook, 'redirectPopupToTopic'], $callback);
  }

}
