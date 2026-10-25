<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\aculta_forum\Hook\RestrictedForumMembershipHooks;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumMembershipSync;
use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\group\Entity\GroupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests membership reconciliation triggered by associate marker changes.
 */
#[CoversClass(RestrictedForumMembershipHooks::class)]
final class RestrictedForumMembershipHooksTest extends TestCase {

  /**
   * A saved marker with a uid reconciles that account.
   */
  public function testMarkerWithUidIsSynchronised(): void {
    $hooks = $this->hooks($this->lockExpectingUid(5));
    $hooks->associadoUpdate($this->marker('aculta_associado', [['target_id' => 5]]));
  }

  /**
   * Insert also reconciles.
   */
  public function testInsertAlsoSynchronises(): void {
    $hooks = $this->hooks($this->lockExpectingUid(5));
    $hooks->associadoInsert($this->marker('aculta_associado', [['target_id' => 5]]));
  }

  /**
   * Delete also reconciles, since the uid remains in the loaded object.
   */
  public function testDeleteAlsoSynchronises(): void {
    $hooks = $this->hooks($this->lockExpectingUid(5));
    $hooks->associadoDelete($this->marker('aculta_associado', [['target_id' => 5]]));
  }

  /**
   * Entities that are not fieldable are ignored.
   */
  public function testNonFieldableEntityIsIgnored(): void {
    $hooks = $this->hooks($this->lockNeverAcquiring());
    $hooks->associadoUpdate($this->createMock(EntityInterface::class));
  }

  /**
   * Other entity types are ignored.
   */
  public function testOtherEntityTypeIsIgnored(): void {
    $marca = $this->createMock(FieldableEntityInterface::class);
    $marca->method('getEntityTypeId')->willReturn('user');
    $marca->expects(self::never())->method('get');
    $hooks = $this->hooks($this->lockNeverAcquiring());
    $hooks->associadoUpdate($marca);
  }

  /**
   * A marker without the uid field is ignored.
   */
  public function testMissingUidFieldIsIgnored(): void {
    $marca = $this->createMock(FieldableEntityInterface::class);
    $marca->method('getEntityTypeId')->willReturn('aculta_associado');
    $marca->method('hasField')->with('uid')->willReturn(FALSE);
    $marca->expects(self::never())->method('get');
    $hooks = $this->hooks($this->lockNeverAcquiring());
    $hooks->associadoUpdate($marca);
  }

  /**
   * An empty uid field does not reconcile anybody.
   */
  public function testEmptyUidIsIgnored(): void {
    $hooks = $this->hooks($this->lockNeverAcquiring());
    $hooks->associadoUpdate($this->marker('aculta_associado', []));
  }

  /**
   * A zero uid does not reconcile anybody.
   */
  public function testZeroUidIsIgnored(): void {
    $hooks = $this->hooks($this->lockNeverAcquiring());
    $hooks->associadoUpdate($this->marker('aculta_associado', [['target_id' => 0]]));
  }

  /**
   * Builds the hooks over a real synchronisation service.
   *
   * A membership sync with one configured group reaches the lock only after
   * the uid check, so the lock expectation proves which uid was forwarded.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Lock fixture with the expected acquisition.
   */
  private function hooks(LockBackendInterface $lock): RestrictedForumMembershipHooks {
    $resolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $resolver->method('groupsOfType')->willReturn([3 => $this->createMock(GroupInterface::class)]);
    $sync = new RestrictedForumMembershipSync(
      $this->createMock(EntityTypeManagerInterface::class),
      $resolver,
      $this->createMock(ActiveAssociateInterface::class),
      $lock,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    return new RestrictedForumMembershipHooks($sync);
  }

  /**
   * Returns a lock that must be acquired for the given uid.
   */
  private function lockExpectingUid(int $uid): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    // FALSE encerra a sincronização logo após o lock, sem tocar no Group.
    $lock->expects(self::once())->method('acquire')
      ->with('aculta_forum_membership_sync:' . $uid, 5.0)
      ->willReturn(FALSE);
    $lock->expects(self::never())->method('release');
    return $lock;
  }

  /**
   * Returns a lock that must never be acquired.
   */
  private function lockNeverAcquiring(): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::never())->method('acquire');
    return $lock;
  }

  /**
   * Returns an associate marker fixture with the given uid values.
   *
   * @param string $entityTypeId
   *   Tipo da entidade devolvido pela marca.
   * @param array<int, array<string, int>> $values
   *   Valores do campo uid.
   */
  private function marker(string $entityTypeId, array $values): FieldableEntityInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn($values);
    $marca = $this->createMock(FieldableEntityInterface::class);
    $marca->method('getEntityTypeId')->willReturn($entityTypeId);
    $marca->method('hasField')->with('uid')->willReturn(TRUE);
    $marca->method('get')->with('uid')->willReturn($field);
    return $marca;
  }

}
