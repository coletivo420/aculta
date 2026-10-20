<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\ContentPurposeResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('aculta_portal')]
final class ContentPurposeResolverTest extends TestCase {

  public static function forumBundles(): array {
    return [
      'ACULTA Forum topic' => ['aculta_forum_topic'],
    ];
  }

  // Só o bundle próprio pertence ao purpose FORUM; o bundle legado 'forum' saiu da regra (commit 4fa73cc0).
  #[DataProvider('forumBundles')]
  public function testPublicTopicCanonicalRouteBelongsToForumPurpose(string $bundle): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $request = Request::create('/node/123');
    $request->attributes->set('_route', 'entity.node.canonical');
    $request->attributes->set('node', $node);
    $resolver = new ContentPurposeResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->domainPurposeManager(),
    );

    $this->assertSame('forum', $resolver->canonicalContentPurpose($request));
  }

  private function domainPurposeManager(): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
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
