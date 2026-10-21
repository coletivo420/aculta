<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Hook;

use Drupal\aculta_forum\Hook\ForumPostAccessHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\Entity\Node;
use Drupal\user\EntityOwnerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests creation access for revisionable public Forum posts.
 */
#[Group('aculta_forum')]
final class ForumPostAccessHooksTest extends TestCase {

  /**
   * Creation requires both the Forum purpose and the dedicated permission.
   */
  public function testCreateAccessRequiresForumPurposeAndPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('create aculta forum posts')->willReturn(TRUE);
    $hooks = new ForumPostAccessHooks($this->domainPurposeManager('forum_aculta_org'));
    $context = ['entity_type_id' => 'aculta_forum_post'];

    self::assertTrue($hooks->entityCreateAccess($account, $context, 'aculta_forum_post')?->isAllowed());
    self::assertTrue((new ForumPostAccessHooks($this->domainPurposeManager('aculta_org')))
      ->entityCreateAccess($account, $context, 'aculta_forum_post')?->isForbidden());

    $accountWithoutPermission = $this->createMock(AccountInterface::class);
    $accountWithoutPermission->method('hasPermission')->willReturn(FALSE);
    self::assertTrue($hooks->entityCreateAccess($accountWithoutPermission, $context, 'aculta_forum_post')?->isForbidden());
  }

  /**
   * Public owner access requires the matching published Forum context.
   */
  public function testOwnerReadAndUpdateAccessIsContextual(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(17);
    $account->method('hasPermission')->willReturn(FALSE);
    $hooks = new ForumPostAccessHooks($this->domainPurposeManager('forum_aculta_org'));
    $post = $this->post($this->topic(TRUE), 17);

    self::assertTrue($hooks->entityAccess($post, 'update', $account)?->isAllowed());
    self::assertTrue($hooks->entityAccess($post, 'update', $this->account(18))?->isNeutral());
    $wrongPurpose = new ForumPostAccessHooks($this->domainPurposeManager('aculta_org'));
    self::assertTrue($wrongPurpose->entityAccess($post, 'update', $account)?->isForbidden());
    self::assertTrue($hooks->entityAccess($this->post($this->topic(FALSE), 17), 'update', $account)?->isForbidden());
  }

  /**
   * The entity admin grant applies in MAIN and without a purpose (CLI, cron).
   *
   * Outside MAIN the grant does not apply: public rules still allow reading a
   * published Forum post, and every other operation is forbidden.
   */
  public function testAdministratorGrantIsRestrictedOutsideMain(): void {
    $administrator = $this->createMock(AccountInterface::class);
    $administrator->method('hasPermission')
      ->with('administer aculta forum posts')
      ->willReturn(TRUE);
    $post = $this->post($this->topic(TRUE), 17);

    foreach (['view', 'update', 'delete', 'view label'] as $operation) {
      $mainAccess = (new ForumPostAccessHooks($this->domainPurposeManager('aculta_org')))
        ->entityAccess($post, $operation, $administrator);
      self::assertTrue($mainAccess?->isNeutral(), $operation . ' remains governed by the entity admin permission in MAIN.');
      self::assertContains('domain', $mainAccess?->getCacheContexts());

      $withoutPurpose = (new ForumPostAccessHooks($this->domainPurposeManager(NULL)))
        ->entityAccess($post, $operation, $administrator);
      self::assertTrue($withoutPurpose?->isNeutral(), $operation . ' keeps the admin grant without a purpose (CLI, cron).');
    }

    $forum = new ForumPostAccessHooks($this->domainPurposeManager('forum_aculta_org'));
    self::assertTrue($forum->entityAccess($post, 'view', $administrator)?->isAllowed(), 'Public view remains allowed on the Forum.');
    self::assertTrue($forum->entityAccess($post, 'view label', $administrator)?->isAllowed(), 'Public label view remains allowed on the Forum.');
    self::assertTrue($forum->entityAccess($post, 'update', $administrator)?->isForbidden(), 'Update is denied outside MAIN.');
    self::assertTrue($forum->entityAccess($post, 'delete', $administrator)?->isForbidden(), 'Delete is denied outside MAIN.');
  }

  /**
   * Builds the purpose manager around an active Domain ID.
   */
  private function domainPurposeManager(?string $domainId): DomainPurposeManager {
    $domain = NULL;
    if ($domainId !== NULL) {
      $domain = $this->createMock(DomainInterface::class);
      $domain->method('id')->willReturn($domainId);
    }
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);

    return new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

  /**
   * Builds a Node topic in the Forum purpose.
   */
  private function topic(bool $published): Node {
    $topic = $this->createMock(Node::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->with('field_domain_source')->willReturn(TRUE);
    $topic->method('get')->with('field_domain_source')->willReturn(new ForumPostAccessFieldList('forum_aculta_org'));
    $topic->method('isPublished')->willReturn($published);
    $topic->method('access')->willReturn(AccessResult::allowed());
    $topic->method('getCacheTags')->willReturn([]);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);
    return $topic;
  }

  /**
   * Builds a post entity with a contextual topic and owner.
   */
  private function post(Node $topic, int $ownerId): FieldableEntityInterface&EntityOwnerInterface {
    $post = $this->createMock(Node::class);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('isPublished')->willReturn(TRUE);
    $post->method('hasField')->with('topic')->willReturn(TRUE);
    $post->method('get')->with('topic')->willReturn(new ForumPostAccessFieldList($topic));
    $post->method('getOwnerId')->willReturn($ownerId);
    $post->method('getCacheTags')->willReturn([]);
    $post->method('getCacheContexts')->willReturn([]);
    $post->method('getCacheMaxAge')->willReturn(-1);
    return $post;
  }

  /**
   * Builds an account with the requested ID and permissions.
   */
  private function account(int $uid): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->willReturn(FALSE);
    return $account;
  }

}

/**
 * Field item list stand-in exposing a target entity or ID.
 */
final class ForumPostAccessFieldList extends FieldItemList {

  public function __construct(private readonly mixed $target) {}

  /**
   * {@inheritdoc}
   */
  public function __get($property_name): mixed {
    if ($property_name === 'entity' || $property_name === 'target_id') {
      return $this->target;
    }
    return parent::__get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return FALSE;
  }

}
