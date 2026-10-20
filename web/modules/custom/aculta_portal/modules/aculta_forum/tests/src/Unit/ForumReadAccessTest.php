<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Access\ForumReadAccess;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\aculta_forum\Access\ForumReadAccess */
final class ForumReadAccessTest extends TestCase {

  /**
   * @covers ::access */
  public function testAccessRequiresAuthenticatedAccountAndForumPurpose(): void {
    $forumDomain = $this->createMock(DomainInterface::class);
    $forumDomain->method('id')->willReturn('forum_aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $otherDomain = $this->createMock(DomainInterface::class);
    $otherDomain->method('id')->willReturn('aculta_org');
    $negotiator->method('getActiveDomain')->willReturnOnConsecutiveCalls($forumDomain, $otherDomain);
    $manager = new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $checker = new ForumReadAccess($manager);
    $authenticated = $this->createMock(AccountInterface::class);
    $authenticated->method('isAuthenticated')->willReturn(TRUE);
    $anonymous = $this->createMock(AccountInterface::class);
    $anonymous->method('isAuthenticated')->willReturn(FALSE);

    self::assertTrue($checker->access($authenticated)->isAllowed());
    self::assertTrue($checker->access($anonymous)->isForbidden());

    self::assertTrue($checker->access($authenticated)->isForbidden());
  }

}
