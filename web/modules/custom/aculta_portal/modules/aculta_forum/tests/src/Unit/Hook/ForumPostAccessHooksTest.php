<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Hook;

use Drupal\aculta_forum\Hook\ForumPostAccessHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
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
   * Builds the purpose manager around an active Domain ID.
   */
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
