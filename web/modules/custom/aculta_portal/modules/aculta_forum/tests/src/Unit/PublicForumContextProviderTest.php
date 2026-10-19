<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\Provider\PublicForumContextProvider;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('aculta_forum')]
final class PublicForumContextProviderTest extends TestCase {

  public function testSupportsOnlyPublicForumTopicNodes(): void {
    $provider = new PublicForumContextProvider($this->domainPurposeManager('forum_aculta_org'));
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('isNew')->willReturn(FALSE);
    $otherNode = $this->createMock(NodeInterface::class);
    $otherNode->method('bundle')->willReturn('article');

    $this->assertTrue($provider->supports($topic));
    $this->assertFalse($provider->supports($otherNode));
    $this->assertFalse($provider->supports($this->createMock(EntityInterface::class)));
  }

  public function testBuildsStableContextAndKeepsWriteAccessNeutral(): void {
    $provider = new PublicForumContextProvider($this->domainPurposeManager('forum_aculta_org'));
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheTags')->willReturn(['node:topic']);
    $topic->method('getCacheMaxAge')->willReturn(3600);
    $topic->method('access')->with('view', $this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::allowed()->addCacheTags(['node:topic']));
    $url = $this->createMock(Url::class);
    $topic->method('toUrl')->with('canonical')->willReturn($url);

    $context = $provider->getContext($topic, $this->createMock(AccountInterface::class));

    $this->assertSame('public_forum', $context->getType());
    $this->assertSame('public_forum:node:topic-uuid', $context->getStableId());
    $this->assertTrue($context->getReadAccess()->isAllowed());
    $this->assertContains('domain', $context->getReadAccess()->getCacheContexts());
    $this->assertTrue($context->getWriteAccess()->isNeutral());
    $this->assertTrue($context->getModerationAccess()->isNeutral());
    $this->assertContains('node:topic', $context->getCacheTags());
    $this->assertSame($url, $context->getCanonicalUrl());
  }

  public function testRejectsUnpersistedHostEntities(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('isNew')->willReturn(TRUE);
    $this->expectException(\LogicException::class);

    (new PublicForumContextProvider($this->domainPurposeManager('forum_aculta_org')))
      ->getContext($topic, $this->createMock(AccountInterface::class));
  }

  public function testReadAccessIsDeniedOutsideForumPurpose(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('isNew')->willReturn(FALSE);
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheTags')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(3600);
    $topic->method('access')->willReturn(AccessResult::allowed());
    $topic->method('toUrl')->willReturn($this->createMock(Url::class));

    $context = (new PublicForumContextProvider($this->domainPurposeManager('magazine_aculta_org')))
      ->getContext($topic, $this->createMock(AccountInterface::class));

    $this->assertFalse($context->getReadAccess()->isAllowed());
    $this->assertContains('domain', $context->getReadAccess()->getCacheContexts());
  }

  private function domainPurposeManager(string $domainId): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn($domainId);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);
    return new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
