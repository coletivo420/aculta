<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\CoAuthor;

use Drupal\aculta_user\CoAuthor\CoAuthorManager;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes the new read-only membership API without a runtime schema.
 */
#[Group('aculta_user')]
final class CoAuthorManagerTest extends TestCase {

  /**
   * No field means no secondary authors, never fallback to the contrib field.
   */
  public function testMissingFieldDoesNotReadTheContribStorage(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('aculta_coauthors')->willReturn(FALSE);
    $node->expects(self::never())->method('get');

    self::assertSame([], (new CoAuthorManager())->getCoAuthorIds($node));
  }

  /**
   * Invalid IDs, duplicates and the main author never enter membership.
   */
  public function testNormalizesReferencesAndExcludesMainAuthor(): void {
    $node = $this->node(4, [
      ['target_id' => '6'],
      ['target_id' => 6],
      ['target_id' => '4'],
      ['target_id' => '-9'],
      ['target_id' => 'invalid'],
      ['target_id' => 0],
      ['target_id' => 9],
      [],
    ]);

    self::assertSame([6, 9], (new CoAuthorManager())->getCoAuthorIds($node));
  }

  /**
   * Being a co-author does not imply anything for anonymous accounts.
   */
  public function testMembershipRequiresAuthenticatedAccount(): void {
    $manager = new CoAuthorManager();
    $node = $this->node(4, [['target_id' => '6']]);
    self::assertTrue($manager->isCoAuthor($node, $this->account(6, TRUE)));
    self::assertFalse($manager->isCoAuthor($node, $this->account(6, FALSE)));
    self::assertFalse($manager->isCoAuthor($node, $this->account(4, TRUE)));
    self::assertFalse($manager->isCoAuthor($node, $this->account(7, TRUE)));
  }

  /**
   * Builds a node mock backed by the expected new field.
   *
   * @param int $owner
   *   The owner user ID.
   * @param array<int, array<string, mixed>> $values
   *   Raw field values.
   */
  private function node(int $owner, array $values): NodeInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn($values);
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('aculta_coauthors')->willReturn(TRUE);
    $node->method('getOwnerId')->willReturn($owner);
    $node->method('get')->with('aculta_coauthors')->willReturn($field);
    return $node;
  }

  /**
   * Builds a minimal account for membership checks.
   */
  private function account(int $uid, bool $authenticated): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('isAuthenticated')->willReturn($authenticated);
    return $account;
  }

}
