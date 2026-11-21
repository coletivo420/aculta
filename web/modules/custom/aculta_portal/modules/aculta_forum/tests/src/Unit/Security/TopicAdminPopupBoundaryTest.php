<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Security;

use Drupal\aculta_forum\Access\TopicAdminPopupAccess;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * SFC-02: direct topic-popup requests never bypass Group and domain checks.
 */
#[Group('aculta_forum')]
final class TopicAdminPopupBoundaryTest extends UnitTestCase {

  public function testViewableForumTopicIsAcceptedWithMoveGrant(): void {
    [$checker, $account, $topic] = $this->fixture();
    self::assertTrue($checker->access($account, $topic)->isAllowed());
  }

  public function testRestrictedGroupTopicIsDenied(): void {
    [$checker, $account, $topic] = $this->fixture(view: FALSE);
    self::assertFalse($checker->access($account, $topic)->isAllowed());
  }

  public function testForeignSourceDomainIsDenied(): void {
    [$checker, $account, $topic] = $this->fixture(source: 'wiki');
    self::assertFalse($checker->access($account, $topic)->isAllowed());
  }

  public function testWrongRequestDomainIsDenied(): void {
    [$checker, $account, $topic] = $this->fixture(site: SiteKey::Magazine);
    self::assertFalse($checker->access($account, $topic)->isAllowed());
  }

  public function testWikiOrLmsBundleIsNotAForumTopic(): void {
    foreach (['wiki_entry', 'aculta_lms_topic', 'revista'] as $bundle) {
      [$checker, $account, $topic] = $this->fixture(bundle: $bundle);
      self::assertFalse($checker->access($account, $topic)->isAllowed(), $bundle);
    }
  }

  public function testTopicPermissionOnlyIsNotEnough(): void {
    [$checker, $account, $topic] = $this->fixture(grants: ['moderate aculta forum topics']);
    self::assertFalse($checker->access($account, $topic)->isAllowed());
  }

  public function testAnonymousCannotReadPopup(): void {
    [$checker, $account, $topic] = $this->fixture(authenticated: FALSE);
    self::assertFalse($checker->access($account, $topic)->isAllowed());
  }

  public function testMissingTargetIsDenied(): void {
    [$checker, $account] = $this->fixture();
    self::assertFalse($checker->access($account, NULL)->isAllowed());
  }

  public function testRouteEnforcesContextAndDoesNotCachePopup(): void {
    $root = dirname(__DIR__, 4);
    $routes = Yaml::parseFile($root . '/aculta_forum.routing.yml');
    $popup = $routes['aculta_forum.topic_admin_popup'];
    self::assertSame('aculta_forum.topic_admin_popup_access:access', $popup['requirements']['_custom_access']);
    self::assertSame('move aculta forum topics', $popup['requirements']['_permission']);
    self::assertSame('forum', $popup['options']['_aculta_site_key']);
    self::assertTrue($popup['options']['no_cache']);
    self::assertSame(['GET', 'HEAD'], $popup['methods']);
    self::assertSame('entity:node', $popup['options']['parameters']['node']['type']);

    $source = (string) file_get_contents($root . '/src/Controller/TopicAdminPopupController.php');
    self::assertStringContainsString('popupAccess->access(', $source);
    self::assertStringContainsString("ModerationScope::allows(\$account, 'forum')", $source);
    self::assertStringContainsString("'purpose' => 'forum'", $source);
    self::assertStringContainsString("!\$report->access('view', \$account)", $source);
    self::assertStringContainsString("'max-age' => 0", $source);
  }

  /**
   * @param list<string> $grants
   *   Exact granted permissions.
   *
   * @return array{TopicAdminPopupAccess, AccountInterface, NodeInterface}
   *   Service, mock account and mock topic.
   */
  private function fixture(
    string $bundle = 'aculta_forum_topic',
    bool $view = TRUE,
    string $source = 'forum',
    SiteKey $site = SiteKey::Forum,
    array $grants = ['move aculta forum topics'],
    bool $authenticated = TRUE,
  ): array {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $grants, TRUE),
    );

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('getValue')->willReturn([['target_id' => 'source-domain']]);
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn($bundle);
    $topic->method('hasField')->willReturn(TRUE);
    $topic->method('get')->with('field_domain_source')->willReturn($field);
    $topic->method('access')->willReturn($view ? AccessResult::allowed() : AccessResult::forbidden());

    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getCurrentSiteKey')->willReturn($site);
    $sites->method('getSiteKeyValueForDomainId')->willReturn($source);

    return [new TopicAdminPopupAccess($sites), $account, $topic];
  }

}
