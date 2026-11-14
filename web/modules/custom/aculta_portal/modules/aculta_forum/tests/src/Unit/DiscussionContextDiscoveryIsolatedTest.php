<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScope;
use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Context\Provider\LmsDiscussionContextProvider;
use Drupal\aculta_forum\Context\Provider\PublicationContextProvider;
use Drupal\aculta_forum\Context\Provider\PublicForumContextProvider;
use Drupal\aculta_forum\Context\Provider\WikiDiscussionContextProvider;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests isolated discovery and contract of all 4 discussion context providers.
 *
 * Verifies container-level provider aggregation, Wiki presence/absence,
 * single provider resolution per host, and fails-closed behavior for unsupported hosts.
 */
#[Group('aculta_forum')]
final class DiscussionContextDiscoveryIsolatedTest extends TestCase {

  /**
   * Tests resolution of each of the 4 context types and closing on unsupported.
   */
  public function testDiscoversAllFourContextsAndFailsClosed(): void {
    $siteManager = $this->siteManager('forum_aculta_org');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $wikiProvider = new WikiDiscussionContextProvider();
    $publicationProvider = new PublicationContextProvider();
    $publicForumProvider = new PublicForumContextProvider($siteManager, $this->scopeResolver());
    $lmsProvider = new LmsDiscussionContextProvider($entityTypeManager);

    $allProviders = [
      $wikiProvider,
      $publicationProvider,
      $publicForumProvider,
      $lmsProvider,
    ];

    $resolver = new DiscussionContextResolver($allProviders);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $account->method('isAuthenticated')->willReturn(TRUE);

    // 1. Wiki entry
    $wikiNode = $this->createMock(NodeInterface::class);
    $wikiNode->method('bundle')->willReturn('wiki_entry');
    $wikiNode->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_aculta_discussion');
    $wikiNode->method('isNew')->willReturn(FALSE);
    $wikiNode->method('uuid')->willReturn('wiki-uuid-1');
    $wikiNode->method('getCacheContexts')->willReturn([]);
    $wikiNode->method('getCacheTags')->willReturn([]);
    $wikiNode->method('getCacheMaxAge')->willReturn(3600);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => 2]]);
    $field->method('access')->willReturn(AccessResult::allowed());
    $wikiNode->method('get')->with('field_aculta_discussion')->willReturn($field);
    $wikiNode->method('access')->willReturn(AccessResult::allowed());
    $wikiNode->method('toUrl')->willReturn($this->createMock(Url::class));

    $context = $resolver->resolve($wikiNode, $account);
    self::assertNotNull($context);
    self::assertSame('wiki', $context->getType());
    self::assertSame('wiki:node:wiki-uuid-1', $context->getStableId());

    // 2. Revista (publication)
    $revistaNode = $this->createMock(NodeInterface::class);
    $revistaNode->method('bundle')->willReturn('revista');
    $revistaNode->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_aculta_discussion');
    $revistaNode->method('isNew')->willReturn(FALSE);
    $revistaNode->method('uuid')->willReturn('revista-uuid-1');
    $revistaNode->method('getCacheContexts')->willReturn([]);
    $revistaNode->method('getCacheTags')->willReturn([]);
    $revistaNode->method('getCacheMaxAge')->willReturn(3600);
    $revistaNode->method('get')->with('field_aculta_discussion')->willReturn($field);
    $revistaNode->method('access')->willReturn(AccessResult::allowed());
    $revistaNode->method('toUrl')->willReturn($this->createMock(Url::class));

    $context = $resolver->resolve($revistaNode, $account);
    self::assertNotNull($context);
    self::assertSame('publication', $context->getType());
    self::assertSame('publication:node:revista-uuid-1', $context->getStableId());

    // 3. Fórum público
    $forumNode = $this->createMock(NodeInterface::class);
    $forumNode->method('bundle')->willReturn('aculta_forum_topic');
    $forumNode->method('hasField')->willReturn(FALSE);
    $forumNode->method('isNew')->willReturn(FALSE);
    $forumNode->method('uuid')->willReturn('forum-uuid-1');
    $forumNode->method('getCacheContexts')->willReturn([]);
    $forumNode->method('getCacheTags')->willReturn([]);
    $forumNode->method('getCacheMaxAge')->willReturn(3600);
    $forumNode->method('access')->willReturn(AccessResult::allowed());
    $forumNode->method('toUrl')->willReturn($this->createMock(Url::class));

    $context = $resolver->resolve($forumNode, $account);
    self::assertNotNull($context);
    self::assertSame('public_forum', $context->getType());
    self::assertSame('public_forum:node:forum-uuid-1', $context->getStableId());

    // 4. Unsupported host fails closed (returns NULL)
    $article = $this->createMock(NodeInterface::class);
    $article->method('bundle')->willReturn('article');
    $article->method('hasField')->willReturn(FALSE);
    self::assertNull($resolver->resolve($article, $account));

    $plainEntity = $this->createMock(EntityInterface::class);
    self::assertNull($resolver->resolve($plainEntity, $account));
  }

  /**
   * Tests container without Wiki provider: Wiki entries fail closed cleanly.
   */
  public function testContainerWithoutWikiFailsClosedOnWikiEntry(): void {
    $siteManager = $this->siteManager('forum_aculta_org');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $providersWithoutWiki = [
      new PublicationContextProvider(),
      new PublicForumContextProvider($siteManager, $this->scopeResolver()),
      new LmsDiscussionContextProvider($entityTypeManager),
    ];

    $resolver = new DiscussionContextResolver($providersWithoutWiki);
    $account = $this->createMock(AccountInterface::class);

    $wikiNode = $this->createMock(NodeInterface::class);
    $wikiNode->method('bundle')->willReturn('wiki_entry');
    $wikiNode->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_aculta_discussion');

    self::assertNull($resolver->resolve($wikiNode, $account));
  }

  /**
   * Tests that exactly one provider matches each host (no overlap/ambiguity).
   */
  public function testEachHostMatchesExactlyOneProvider(): void {
    $siteManager = $this->siteManager('forum_aculta_org');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $providers = [
      'wiki' => new WikiDiscussionContextProvider(),
      'publication' => new PublicationContextProvider(),
      'public_forum' => new PublicForumContextProvider($siteManager, $this->scopeResolver()),
      'lms' => new LmsDiscussionContextProvider($entityTypeManager),
    ];

    $hosts = [
      'wiki_entry' => function () {
        $n = $this->createMock(NodeInterface::class);
        $n->method('bundle')->willReturn('wiki_entry');
        $n->method('hasField')->willReturnCallback(static fn (string $f): bool => $f === 'field_aculta_discussion');
        return $n;
      },
      'revista' => function () {
        $n = $this->createMock(NodeInterface::class);
        $n->method('bundle')->willReturn('revista');
        $n->method('hasField')->willReturnCallback(static fn (string $f): bool => $f === 'field_aculta_discussion');
        return $n;
      },
      'aculta_forum_topic' => function () {
        $n = $this->createMock(NodeInterface::class);
        $n->method('bundle')->willReturn('aculta_forum_topic');
        $n->method('hasField')->willReturn(FALSE);
        return $n;
      },
      'aculta_lms_topic' => function () {
        $n = $this->createMock(NodeInterface::class);
        $n->method('bundle')->willReturn('aculta_lms_topic');
        $n->method('hasField')->willReturnCallback(static fn (string $f): bool => $f === 'field_aculta_discussion');
        return $n;
      },
    ];

    foreach ($hosts as $hostType => $factory) {
      $host = $factory();
      $matches = [];
      foreach ($providers as $providerName => $provider) {
        if ($provider->supports($host)) {
          $matches[] = $providerName;
        }
      }
      self::assertCount(1, $matches, sprintf("Host '%s' must match exactly 1 provider, matched: %s", $hostType, implode(', ', $matches)));
    }
  }

  /**
   * Creates a Domain purpose manager for the requested domain ID.
   */
  private function siteManager(string $domainId): DomainSiteManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn($domainId);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);
    return new DomainSiteManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }


  /**
   * Creates a scope resolver returning a public forum classification.
   */
  private function scopeResolver(): ForumAnalyticsScopeResolver {
    $resolver = $this->createMock(ForumAnalyticsScopeResolver::class);
    $resolver->method('resolveHost')->willReturn(new ForumAnalyticsScope(ForumAnalyticsScope::PUBLIC_FORUM));
    return $resolver;
  }

}
