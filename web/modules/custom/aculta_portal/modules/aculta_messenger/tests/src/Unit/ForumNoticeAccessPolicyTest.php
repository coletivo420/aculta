<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies access and domain checks for public Forum notice recipients.
 */
#[Group('aculta_messenger')]
final class ForumNoticeAccessPolicyTest extends UnitTestCase {

  /**
   * A published topic is visible only through its assigned Forum domain.
   */
  public function testCurrentForumDomainAndNodeAccessAreRequired(): void {
    [$policy, $topic, $account] = $this->fixture('forum_aculta_org', AccessResult::allowed());
    $topic->expects($this->once())->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());

    self::assertTrue($policy->canViewTopic($topic, 42));
  }

  /**
   * A topic assigned to a different domain is never accepted as public Forum.
   */
  public function testDifferentDomainIsRejectedBeforeNodeAccess(): void {
    [$policy, $topic] = $this->fixture('wiki420_aculta_org', AccessResult::allowed());
    $topic->expects($this->never())->method('access');

    self::assertFalse($policy->canViewTopic($topic, 42));
  }

  /**
   * The recipient's current Drupal node access must allow viewing.
   */
  public function testForbiddenNodeAccessIsRejected(): void {
    [$policy, $topic, $account] = $this->fixture('forum_aculta_org', AccessResult::forbidden());
    $topic->expects($this->once())->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::forbidden());

    self::assertFalse($policy->canViewTopic($topic, 42));
  }

  /**
   * Creates the Forum domain, active recipient, and topic fixtures.
   */
  private function fixture(string $assignedDomain, AccessResult $access): array {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
    $domains = $this->createMock(EntityStorageInterface::class);
    $domains->method('load')->with('forum_aculta_org')->willReturn($domain);

    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with(42)->willReturn($account);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'domain' => $domains,
      'user' => $users,
      default => throw new \LogicException('Unexpected entity type storage request.'),
    });

    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);
    $negotiator->method('setActiveDomain')->willReturn(NULL);
    $purposeManager = new DomainPurposeManager(
      $entityTypeManager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    $source = $this->createMock(FieldItemListInterface::class);
    $source->method('isEmpty')->willReturn(FALSE);
    $source->method('__get')->with('target_id')->willReturn($assignedDomain);
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('isPublished')->willReturn(TRUE);
    $topic->method('hasField')->willReturnCallback(static fn(string $name): bool => $name === 'field_domain_source');
    $topic->method('get')->with('field_domain_source')->willReturn($source);
    $topic->method('access')->willReturn($access);

    return [new ForumNoticeAccessPolicy($entityTypeManager, $purposeManager), $topic, $account];
  }

}
