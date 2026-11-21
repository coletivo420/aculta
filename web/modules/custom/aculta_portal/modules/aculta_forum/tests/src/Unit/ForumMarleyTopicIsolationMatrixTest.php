<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumMarleyTopicActions;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Marley topic controls against authorization and source-domain leaks.
 *
 * Tests are kept in their own file so the main Forum implementation can
 * evolve concurrently without conflicting file edits.
 */
#[Group('aculta_forum')]
final class ForumMarleyTopicIsolationMatrixTest extends UnitTestCase {

  #[DataProvider('accessCases')]
  public function testAccessMatrix(
    bool $topicGrant,
    bool $anonymous,
    string $bundle,
    string $domainPurpose,
    bool $canView,
    bool $expected,
  ): void {
    $account = $this->account($topicGrant, $anonymous);
    $topic = $this->topic($bundle, $canView);
    $sites = $this->site($domainPurpose);
    $actions = new ForumMarleyTopicActions($sites, $this->createMock(TopicAuthorDeletion::class));

    self::assertSame($expected, $actions->access($account, $topic)->isAllowed());
  }

  /**
   * @return iterable<string, array{bool, bool, string, string, bool, bool}>
   *   Authorized and rejected paths through the Forum service.
   */
  public static function accessCases(): iterable {
    yield 'authorized current forum topic' => [TRUE, FALSE, 'aculta_forum_topic', 'forum', TRUE, TRUE];
    yield 'missing topic grant' => [FALSE, FALSE, 'aculta_forum_topic', 'forum', TRUE, FALSE];
    yield 'anonymous despite grant' => [TRUE, TRUE, 'aculta_forum_topic', 'forum', TRUE, FALSE];
    yield 'forum grant cannot access wiki host' => [TRUE, FALSE, 'wiki_entry', 'forum', TRUE, FALSE];
    yield 'forum grant cannot access magazine host' => [TRUE, FALSE, 'revista', 'forum', TRUE, FALSE];
    yield 'wrong domain courses' => [TRUE, FALSE, 'aculta_forum_topic', 'courses', TRUE, FALSE];
    yield 'wrong domain wiki' => [TRUE, FALSE, 'aculta_forum_topic', 'wiki', TRUE, FALSE];
    yield 'private forum node' => [TRUE, FALSE, 'aculta_forum_topic', 'forum', FALSE, FALSE];
  }

  public function testNullTopicNeverGrantsAccess(): void {
    $actions = new ForumMarleyTopicActions(
      $this->site('forum'),
      $this->createMock(TopicAuthorDeletion::class),
    );
    self::assertFalse($actions->access($this->account(TRUE), NULL)->isAllowed());
  }

  public function testUnavailableOwnerDomainDeniesByDefault(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getSiteKeyValueForDomainId')->willReturn('forum');
    $sites->method('runInSite')->willReturn(NULL);
    $actions = new ForumMarleyTopicActions($sites, $this->createMock(TopicAuthorDeletion::class));

    self::assertFalse($actions->access($this->account(TRUE), $this->topic('aculta_forum_topic', TRUE))->isAllowed());
  }

  public function testDeniedAccessCannotProduceLinks(): void {
    $actions = new ForumMarleyTopicActions(
      $this->site('forum'),
      $this->createMock(TopicAuthorDeletion::class),
    );
    self::assertSame(
      [],
      $actions->buildActions($this->topic('aculta_forum_topic', TRUE), $this->account(FALSE)),
    );
  }

  private function topic(string $bundle, bool $canView): NodeInterface {
    $domain = $this->createMock(FieldItemListInterface::class);
    $domain->method('isEmpty')->willReturn(FALSE);
    $domain->method('__get')->with('target_id')->willReturn('domain-forum');

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn($bundle);
    $topic->method('hasField')->willReturn(TRUE);
    $topic->method('get')->with('field_domain_source')->willReturn($domain);
    $topic->method('access')->willReturn($canView ? AccessResult::allowed() : AccessResult::forbidden());
    return $topic;
  }

  private function site(string $purpose): DomainSiteManagerInterface {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getSiteKeyValueForDomainId')->with('domain-forum')->willReturn($purpose);
    $sites->method('runInSite')->willReturnCallback(
      static fn (string $siteKey, callable $callback): mixed => $callback(),
    );
    return $sites;
  }

  private function account(bool $grant, bool $anonymous = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(!$anonymous);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $grant && $permission === 'moderate aculta forum topics',
    );
    return $account;
  }

}
