<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\MarleyListingBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Characterizes access to individual revisions in the Studio listing.
 */
#[CoversClass(MarleyListingBuilder::class)]
#[Group('aculta_studio')]
final class MarleyListingRevisionAccessTest extends UnitTestCase {

  /**
   * A visible node does not authorize a revision the account cannot view.
   */
  public function testVisibleNodeDoesNotAuthorizeUnviewableRevision(): void {
    $account = $this->createMock(AccountInterface::class);
    $revision = $this->createMock(NodeInterface::class);
    $revision->expects(self::exactly(2))
      ->method('access')
      ->willReturnCallback(static fn (string $operation): bool => $operation === 'view');

    self::assertFalse(MarleyListingBuilder::canViewRevision($revision, $account));
  }

  /**
   * A revision row needs both the node and the revision access to allow it.
   */
  public function testBothAccessDecisionsMustAllow(): void {
    $account = $this->createMock(AccountInterface::class);
    $revision = $this->createMock(NodeInterface::class);
    $revision->expects(self::exactly(2))
      ->method('access')
      ->willReturn(TRUE);

    self::assertTrue(MarleyListingBuilder::canViewRevision($revision, $account));
  }

  /**
   * A denied node short-circuits the revision access check.
   */
  public function testDeniedNodeShortCircuitsRevisionAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $revision = $this->createMock(NodeInterface::class);
    $revision->expects(self::once())
      ->method('access')
      ->with('view', $account)
      ->willReturn(FALSE);

    self::assertFalse(MarleyListingBuilder::canViewRevision($revision, $account));
  }

}
