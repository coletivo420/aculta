<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_wiki\Access\WikiModerationAccess;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Wiki-only moderation grants never inherit Forum or Marley permissions.
 */
#[Group('aculta_wiki')]
final class WikiModerationAccessTest extends UnitTestCase {

  /**
   * @param list<string> $permissions
   *   Permission matrix for a combined-role account.
   */
  #[DataProvider('permissionCases')]
  public function testAccessMatrix(array $permissions, bool $overview, bool $proposals, bool $entries, bool $reports): void {
    $account = $this->account($permissions);
    $policy = $this->policy(SiteKey::Wiki);
    self::assertSame($overview, $policy->overviewAccess($account)->isAllowed());
    self::assertSame($proposals, $policy->proposalsAccess($account)->isAllowed());
    self::assertSame($entries, $policy->revisionsAccess($account)->isAllowed());
    self::assertSame($reports, $policy->reportsAccess($account)->isAllowed());
  }

  /**
   * @return iterable<string, array{list<string>, bool, bool, bool, bool}>
   *   Purpose-scoped permissions and their expected access.
   */
  public static function permissionCases(): iterable {
    yield 'ordinary account' => [[], FALSE, FALSE, FALSE, FALSE];
    yield 'Forum moderator only' => [
      ['moderate aculta forum comments', 'moderate aculta forum topics', 'moderar denuncias cgu: forum'],
      FALSE, FALSE, FALSE, FALSE,
    ];
    yield 'Wiki editor reviews entries' => [
      ['edit any wiki_entry content', 'use wiki_editorial transition review_to_published'],
      TRUE, FALSE, TRUE, FALSE,
    ];
    yield 'Wiki reviewer returns entries' => [
      ['edit any wiki_entry content', 'use wiki_editorial transition review_to_draft'],
      TRUE, FALSE, TRUE, FALSE,
    ];
    yield 'Entry editor without transition' => [
      ['edit any wiki_entry content'],
      FALSE, FALSE, FALSE, FALSE,
    ];
    yield 'Wiki proposal moderator' => [
      ['moderate wiki proposals', 'view any wiki proposals'],
      TRUE, TRUE, FALSE, FALSE,
    ];
    yield 'Wiki proposals without view grant' => [
      ['moderate wiki proposals'],
      FALSE, FALSE, FALSE, FALSE,
    ];
    yield 'Wiki CGU only' => [
      ['moderar denuncias cgu: wiki'],
      TRUE, FALSE, FALSE, TRUE,
    ];
    yield 'Forum and Wiki CGU combined' => [
      ['moderar denuncias cgu: forum', 'moderar denuncias cgu: wiki'],
      TRUE, FALSE, FALSE, TRUE,
    ];
    yield 'global CGU still purpose-scoped by route' => [
      ['moderar denuncias cgu'],
      TRUE, FALSE, FALSE, TRUE,
    ];
  }

  public function testAnonymousCannotUseWikiModerationEvenWithPermissions(): void {
    $account = $this->account(['moderar denuncias cgu: wiki', 'moderate wiki proposals', 'view any wiki proposals'], FALSE);
    $policy = $this->policy(SiteKey::Wiki);
    self::assertFalse($policy->overviewAccess($account)->isAllowed());
    self::assertFalse($policy->proposalsAccess($account)->isAllowed());
    self::assertFalse($policy->reportsAccess($account)->isAllowed());
  }

  public function testWikiPrivilegesDoNotWorkInForumOrAccountDomain(): void {
    $account = $this->account(['moderar denuncias cgu: wiki', 'moderate wiki proposals', 'view any wiki proposals']);
    foreach ([SiteKey::Forum, SiteKey::Account, SiteKey::Magazine] as $site) {
      $policy = $this->policy($site);
      self::assertFalse($policy->overviewAccess($account)->isAllowed());
      self::assertFalse($policy->proposalsAccess($account)->isAllowed());
      self::assertFalse($policy->reportsAccess($account)->isAllowed());
    }
  }

  public function testMissingReportDoesNotGrantAccess(): void {
    $policy = $this->policy(SiteKey::Wiki);
    self::assertFalse($policy->reportAccess($this->account(['moderar denuncias cgu: wiki']))->isAllowed());
  }

  public function testWikiHostMustHaveCorrectBundleAndOwnerDomain(): void {
    $policy = $this->policy(SiteKey::Wiki, 'wiki');
    self::assertTrue($policy->isWikiHost($this->node('wiki_entry')));
    self::assertFalse($policy->isWikiHost($this->node('aculta_forum_topic')));
    self::assertFalse($policy->isWikiHost($this->node('revista')));
    self::assertFalse($this->policy(SiteKey::Wiki, 'forum')->isWikiHost($this->node('wiki_entry')));
  }

  private function account(array $permissions, bool $authenticated = TRUE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    return $account;
  }

  private function policy(SiteKey $site, string $sourceSite = 'wiki'): WikiModerationAccess {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getCurrentSiteKey')->willReturn($site);
    $sites->method('getSiteKeyValueForDomainId')->willReturn($sourceSite);
    return new WikiModerationAccess($sites, $this->createMock(EntityTypeManagerInterface::class));
  }

  private function node(string $bundle): NodeInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('getValue')->willReturn([['target_id' => 'domain-wiki']]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->with('field_domain_source')->willReturn($field);
    return $node;
  }

}
