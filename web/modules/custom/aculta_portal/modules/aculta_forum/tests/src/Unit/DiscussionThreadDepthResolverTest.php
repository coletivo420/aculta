<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\DiscussionThreadDepthResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;

/**
 * Tests DiscussionThreadDepthResolver for depth calculation and normalization.
 *
 * @coversDefaultClass \Drupal\aculta_forum\Service\DiscussionThreadDepthResolver
 * @group aculta_forum
 */
class DiscussionThreadDepthResolverTest extends UnitTestCase {

  protected function createResolver(int $maxDepth): DiscussionThreadDepthResolver {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('reply_max_depth')
      ->willReturn($maxDepth);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('aculta_forum.settings')
      ->willReturn($config);

    return new DiscussionThreadDepthResolver($configFactory);
  }

  public function testResolveDepthWithMaxDepth2(): void {
    $resolver = $this->createResolver(2);

    $c0 = $this->createMock(CommentInterface::class);
    $c0->method('getThread')->willReturn('01/');
    $this->assertSame(0, $resolver->resolveDepth($c0));

    $c1 = $this->createMock(CommentInterface::class);
    $c1->method('getThread')->willReturn('01.00/');
    $this->assertSame(1, $resolver->resolveDepth($c1));

    $c2 = $this->createMock(CommentInterface::class);
    $c2->method('getThread')->willReturn('01.00.00/');
    $this->assertSame(2, $resolver->resolveDepth($c2));

    $c3 = $this->createMock(CommentInterface::class);
    $c3->method('getThread')->willReturn('01.00.00.00/');
    $this->assertSame(2, $resolver->resolveDepth($c3));
  }

  public function testResolveDepthWithMaxDepthDisabled(): void {
    $resolver = $this->createResolver(0);

    $c = $this->createMock(CommentInterface::class);
    $c->method('getThread')->willReturn('01.00.00/');
    $this->assertSame(0, $resolver->resolveDepth($c));
  }

  public function testResolveEffectiveParentIdMaxDepth2(): void {
    $resolver = $this->createResolver(2);

    $this->assertNull($resolver->resolveEffectiveParentId(null));

    // Root parent (depth 0, id=10).
    $p0 = $this->createMock(CommentInterface::class);
    $p0->method('id')->willReturn(10);
    $p0->method('getThread')->willReturn('01/');
    $this->assertSame(10, $resolver->resolveEffectiveParentId($p0));

    // Level 1 parent (depth 1, id=20).
    $p1 = $this->createMock(CommentInterface::class);
    $p1->method('id')->willReturn(20);
    $p1->method('getThread')->willReturn('01.00/');
    $this->assertSame(20, $resolver->resolveEffectiveParentId($p1));

    // Level 2 parent (depth 2, id=30), whose parent is p1 (depth 1, id=20).
    $p2 = $this->createMock(CommentInterface::class);
    $p2->method('id')->willReturn(30);
    $p2->method('getThread')->willReturn('01.00.00/');
    $p2->method('hasParentComment')->willReturn(true);
    $p2->method('getParentComment')->willReturn($p1);

    // Replying to p2 must normalize parent to p1 (id=20) so the reply stays at level 2.
    $this->assertSame(20, $resolver->resolveEffectiveParentId($p2));
  }

  public function testResolveEffectiveParentIdWhenDisabled(): void {
    $resolver = $this->createResolver(0);

    $p0 = $this->createMock(CommentInterface::class);
    $p0->method('id')->willReturn(10);
    $p0->method('getThread')->willReturn('01/');

    $this->assertNull($resolver->resolveEffectiveParentId($p0));
  }

}
