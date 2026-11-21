<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Security;

use Drupal\aculta_forum\Access\CommentModerationAccess;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * RED security tests for audited authorization gaps at the Forum/CGU border.
 *
 * These are deliberately expected to FAIL against the frozen 2026-11-21
 * snapshot, and must not be merged into the shared PR before remediation.
 * Once the access policies are fixed, make this suite part of normal CI.
 */
#[Group('aculta_forum')]
#[Group('security_audit_red')]
final class ForumCguAuthorizationRedTest extends UnitTestCase {

  /**
   * A Forum moderator cannot operate on a comment the account cannot view.
   */
  public function testRestrictedCommentCannotBeModeratedByForumRole(): void {
    $account = $this->forumModerator();
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('access')->willReturn(AccessResult::forbidden());
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');
    $host->method('access')->willReturn(AccessResult::forbidden());
    $comment->method('getCommentedEntity')->willReturn($host);

    $result = (new CommentModerationAccess())->access($account, $this->hxRequest(), $comment);
    self::assertFalse($result->isAllowed(), 'Access to a restricted target must not be granted solely by a global Forum role.');
  }

  /**
   * A Forum-only permission cannot moderate a Wiki or Revista host.
   */
  public function testForumRoleDoesNotModerateOtherDiscussionPurposes(): void {
    $account = $this->forumModerator();
    foreach (['wiki_entry', 'revista'] as $bundle) {
      $host = $this->createMock(NodeInterface::class);
      $host->method('bundle')->willReturn($bundle);
      $comment = $this->createMock(CommentInterface::class);
      $comment->method('getCommentedEntity')->willReturn($host);
      $result = (new CommentModerationAccess())->access($account, $this->hxRequest(), $comment);
      self::assertFalse($result->isAllowed(), 'Forum role must not authorize host bundle ' . $bundle);
    }
  }

  /**
   * The UI header by itself must never be treated as authorization.
   */
  public function testHeaderDoesNotGrantPermissionWithoutRole(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $comment = $this->createMock(CommentInterface::class);
    self::assertFalse((new CommentModerationAccess())->access($account, $this->hxRequest(), $comment)->isAllowed());
  }

  /**
   * Topic moderation popup must enforce entity access, not only a role grant.
   */
  public function testPopupRouteRequiresEntityAwareAccessCheck(): void {
    $routing = Yaml::parseFile(dirname(__DIR__, 4) . '/aculta_forum.routing.yml');
    $requirements = $routing['aculta_forum.topic_admin_popup']['requirements'];
    self::assertTrue(
      isset($requirements['_custom_access']) || isset($requirements['_entity_access']),
      'The popup route currently lacks an entity-aware access gate.',
    );
  }

  /**
   * A previously issued author token must not bypass revoked host access.
   */
  public function testCommentDeleteRechecksTargetVisibility(): void {
    $source = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Controller/DiscussionCommentDeleteController.php');
    self::assertTrue(
      str_contains($source, '$comment->access(') || str_contains($source, '$host->access('),
      'Comment deletion must recheck the current target/host access after Group membership revocation.',
    );
  }

  /**
   * Use a real HTTP request with a forgeable HX header.
   */
  private function hxRequest(): Request {
    $request = Request::create('/discussoes/123/controles', 'GET');
    $request->headers->set('HX-Request', 'true');
    return $request;
  }

  private function forumModerator(): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $permission === 'moderate aculta forum comments',
    );
    return $account;
  }

}
