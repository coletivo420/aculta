<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('aculta_forum')]
final class DiscussionContextResolverTest extends TestCase {

  public function testReturnsNullWhenNoProviderSupportsEntity(): void {
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->willReturn(FALSE);
    $resolver = new DiscussionContextResolver([$provider]);

    $this->assertNull($resolver->resolve(
      $this->createMock(EntityInterface::class),
      $this->createMock(AccountInterface::class),
    ));
  }

  public function testResolvesExactlyOneMatchingProvider(): void {
    $entity = $this->createMock(EntityInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $context = $this->createMock(DiscussionContextInterface::class);
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->expects($this->once())->method('supports')->with($entity)->willReturn(TRUE);
    $provider->expects($this->once())->method('getContext')->with($entity, $account)->willReturn($context);

    $this->assertSame($context, (new DiscussionContextResolver([$provider]))->resolve($entity, $account));
  }

  public function testAmbiguousProvidersFailExplicitly(): void {
    $entity = $this->createMock(EntityInterface::class);
    $providers = [];
    foreach (range(1, 2) as $_) {
      $provider = $this->createMock(DiscussionContextProviderInterface::class);
      $provider->method('supports')->willReturn(TRUE);
      $providers[] = $provider;
    }
    $this->expectException(LogicException::class);
    (new DiscussionContextResolver($providers))->resolve($entity, $this->createMock(AccountInterface::class));
  }

}
