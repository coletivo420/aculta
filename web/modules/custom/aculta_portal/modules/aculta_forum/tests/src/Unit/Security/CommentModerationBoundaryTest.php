<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Security;

use Drupal\aculta_forum\Access\CommentModerationAccess;
use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * SFC-01: a role never substitutes target, context or Group access.
 */
#[Group('aculta_forum')]
final class CommentModerationBoundaryTest extends UnitTestCase {

  public function testForumModeratorMayModerateViewableCurrentForumComment(): void {
    [$gate, $comment, $account] = $this->fixture();
    self::assertTrue($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testForumRoleCannotModerateWikiOrRevista(): void {
    foreach ([
      ['wiki_entry', SiteKey::Wiki],
      ['revista', SiteKey::Magazine],
      ['aculta_lms_topic', SiteKey::Courses],
    ] as [$bundle, $site]) {
      [$gate, $comment, $account] = $this->fixture(
        bundle: $bundle,
        site: $site,
        origin: DiscussionOrigin::CONTEXTUAL,
      );
      self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed(), $bundle);
    }
  }

  public function testForumAndWikiRoleWithoutGlobalCommentPermissionIsStillDenied(): void {
    [$gate, $comment, $account] = $this->fixture(
      bundle: 'wiki_entry',
      site: SiteKey::Wiki,
      origin: DiscussionOrigin::CONTEXTUAL,
      grants: ['moderate aculta forum comments', 'moderar denuncias cgu: wiki'],
    );
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testRevokedGroupVisibilityDeniesModeration(): void {
    [$gate, $comment, $account] = $this->fixture(hostView: FALSE);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testPrivateCommentDeniesEvenWhenTopicIsReadable(): void {
    [$gate, $comment, $account] = $this->fixture(commentView: FALSE);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testForeignDomainSourceIsDenied(): void {
    [$gate, $comment, $account] = $this->fixture(sourceSite: 'magazine');
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testDifferentRequestDomainIsDenied(): void {
    [$gate, $comment, $account] = $this->fixture(site: SiteKey::Account);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testGrowroomArchiveCannotBeModeratedViaPublicRoute(): void {
    [$gate, $comment, $account] = $this->fixture(origin: DiscussionOrigin::ARCHIVE);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testForgeableHtmxHeaderIsNotSufficient(): void {
    [$gate, $comment, $account] = $this->fixture(grants: []);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testHeaderIsRequiredButNotAuthorization(): void {
    [$gate, $comment, $account] = $this->fixture();
    self::assertFalse($gate->access($account, Request::create('/discussoes/1/controles'), $comment)->isAllowed());
    self::assertTrue($gate->canModerate($comment, $account)->isAllowed());
  }

  public function testContextReadAccessIsRequired(): void {
    [$gate, $comment, $account] = $this->fixture(contextRead: FALSE);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  public function testGlobalCommentAdminRequiresContextPermissionInWiki(): void {
    [$allowed, $comment, $account] = $this->fixture(
      bundle: 'wiki_entry',
      site: SiteKey::Wiki,
      origin: DiscussionOrigin::CONTEXTUAL,
      grants: ['administer comments'],
    );
    self::assertTrue($allowed->access($account, $this->request(), $comment)->isAllowed());

    [$denied, $comment2, $account2] = $this->fixture(
      bundle: 'wiki_entry',
      site: SiteKey::Wiki,
      origin: DiscussionOrigin::CONTEXTUAL,
      grants: ['administer comments'],
      contextModerate: FALSE,
    );
    self::assertFalse($denied->access($account2, $this->request(), $comment2)->isAllowed());
  }

  public function testAnonymousCannotModerateEvenWithGrants(): void {
    [$gate, $comment, $account] = $this->fixture(authenticated: FALSE);
    self::assertFalse($gate->access($account, $this->request(), $comment)->isAllowed());
  }

  /**
   * @param list<string> $grants
   *   Exact permission grants for the account.
   *
   * @return array{CommentModerationAccess, CommentInterface, AccountInterface}
   *   Access policy, persisted-comment mock and account mock.
   */
  private function fixture(
    string $bundle = 'aculta_forum_topic',
    SiteKey $site = SiteKey::Forum,
    string $origin = DiscussionOrigin::CURRENT,
    bool $hostView = TRUE,
    bool $commentView = TRUE,
    string $sourceSite = 'forum',
    bool $contextRead = TRUE,
    bool $contextModerate = TRUE,
    array $grants = ['moderate aculta forum comments'],
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

    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn($bundle);
    $host->method('hasField')->willReturn(TRUE);
    $host->method('get')->with('field_domain_source')->willReturn($field);
    $host->method('access')->willReturn($hostView ? AccessResult::allowed() : AccessResult::forbidden());

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntityTypeId')->willReturn('node');
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('access')->willReturn($commentView ? AccessResult::allowed() : AccessResult::forbidden());

    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getCurrentSiteKey')->willReturn($site);
    $sites->method('getSiteKeyValueForDomainId')->willReturn($sourceSite);

    $origins = $this->createMock(DiscussionOriginResolverInterface::class);
    $origins->method('classify')->willReturn(DiscussionOriginClassification::from($origin, $comment));

    $context = $this->createMock(DiscussionContextInterface::class);
    $context->method('getReadAccess')->willReturn($contextRead ? AccessResult::allowed() : AccessResult::forbidden());
    $context->method('getModerationAccess')->willReturn($contextModerate ? AccessResult::allowed() : AccessResult::forbidden());
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->willReturn(TRUE);
    $provider->method('getContext')->willReturn($context);
    $contexts = new DiscussionContextResolver([$provider]);

    return [new CommentModerationAccess($sites, $origins, $contexts), $comment, $account];
  }

  private function request(): Request {
    $request = Request::create('/discussoes/1/controles');
    $request->headers->set('HX-Request', 'true');
    return $request;
  }

}
