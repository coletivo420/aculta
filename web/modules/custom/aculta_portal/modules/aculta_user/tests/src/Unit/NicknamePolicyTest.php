<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Collision checks share the exact role Profile used by public profile links.
 */
#[CoversClass(NicknamePolicy::class)]
#[Group('aculta_user')]
final class NicknamePolicyTest extends UnitTestCase {

  /**
   * New accounts search all role profiles without excluding a UID.
   */
  public function testAccountCreationChecksOtherProfiles(): void {
    $conditions = [];
    $policy = $this->policy($conditions, 1);
    self::assertTrue($policy->isTaken('pirata'));
    self::assertSame([
      ['type', PerfilDoPapel::TIPOS, 'IN'],
      ['field_nickname', 'pirata', NULL],
    ], $conditions);
  }

  /**
   * Editing ignores one's own role profiles, not other accounts.
   */
  public function testEditingExcludesOnlyOwnAccount(): void {
    $conditions = [];
    $policy = $this->policy($conditions, 0);
    self::assertFalse($policy->isTaken('Pirata', 7));
    self::assertSame([
      ['type', PerfilDoPapel::TIPOS, 'IN'],
      ['field_nickname', 'Pirata', NULL],
      ['uid', 7, '<>'],
    ], $conditions);
  }

  /**
   * Creates a read-only Entity Query mock with no bypass of accessCheck(FALSE).
   */
  private function policy(array &$conditions, int $count): NicknamePolicy {
    $query = $this->createMock(QueryInterface::class);
    $query->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnCallback(static function ($field, $value, $operator = NULL) use (&$conditions, $query) {
      $conditions[] = [$field, $value, $operator];
      return $query;
    });
    $query->method('range')->with(0, 1)->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('profile')->willReturn($storage);
    return new NicknamePolicy($entities);
  }

}
