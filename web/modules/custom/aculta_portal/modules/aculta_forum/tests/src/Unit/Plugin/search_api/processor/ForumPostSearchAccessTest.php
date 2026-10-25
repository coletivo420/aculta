<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Plugin\search_api\processor;

use Drupal\aculta_forum\Plugin\search_api\processor\ForumPostSearchAccess;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeGrantsHelper;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\Query;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Forum search grants and paging conditions by account.
 */
#[Group('aculta_forum')]
final class ForumPostSearchAccessTest extends TestCase {

  /**
   * Anonymous grants add the public grant value only.
   */
  public function testAnonymousGetsPublicGrantCondition(): void {
    $query = $this->query();
    $query->setOption('search_api_access_account', $this->account());
    $this->processor(['all' => [0]])->preprocessSearchQuery($query);

    self::assertFalse($query->wasAborted());
    $grants = $this->grantCondition($query);
    self::assertNotNull($grants);
    self::assertContains('node_access__all', $grants);
    self::assertContains('node_access_all:0', $grants);
    self::assertNotContains('node_access_aculta_forum_restricted:69', $grants);
  }

  /**
   * An active member adds the restricted Group grant to the condition.
   */
  public function testMemberGetsRestrictedGroupGrant(): void {
    $query = $this->query();
    $query->setOption('search_api_access_account', $this->account());
    $this->processor([
      'all' => [0],
      'aculta_forum_restricted' => [69],
    ])->preprocessSearchQuery($query);

    $grants = $this->grantCondition($query);
    self::assertNotNull($grants);
    self::assertContains('node_access__all', $grants);
    self::assertContains('node_access_all:0', $grants);
    self::assertContains('node_access_aculta_forum_restricted:69', $grants);
  }

  /**
   * An account without access content aborts the query before paging.
   */
  public function testAccountWithoutAccessContentAborts(): void {
    $query = $this->query();
    $query->setOption('search_api_access_account', $this->account(FALSE));
    $this->processor(['all' => [0]])->preprocessSearchQuery($query);

    self::assertTrue($query->wasAborted());
  }

  /**
   * A bypass account does not receive the grants condition.
   */
  public function testBypassSkipsGrantCondition(): void {
    $query = $this->query();
    $query->setOption('search_api_access_account', $this->account());
    $query->setOption('search_api_bypass_access', TRUE);
    $this->processor(['all' => [0], 'aculta_forum_restricted' => [69]])
      ->preprocessSearchQuery($query);

    self::assertNull($this->grantCondition($query));
  }

  /**
   * Builds a real Search API query on the Forum index.
   */
  private function query(): Query {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('aculta_forum');
    $index->method('status')->willReturn(TRUE);
    $index->method('label')->willReturn('Forum search');
    return new Query($index);
  }

  /**
   * Builds the processor with the requested Node grants.
   *
   * @param array $grants
   *   Grant map returned by NodeGrantsHelper.
   */
  private function processor(array $grants): ForumPostSearchAccess {
    $helper = $this->createMock(NodeGrantsHelper::class);
    $helper->method('nodeAccessGrants')->willReturn($grants);
    $purpose = new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    return new ForumPostSearchAccess(
      [],
      'forum_post_search_access',
      ['id' => 'forum_post_search_access', 'label' => 'Forum post search access'],
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $helper,
      $this->createMock(Connection::class),
      $purpose,
    );
  }

  /**
   * Builds an account with or without access content.
   */
  private function account(bool $access_content = TRUE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $permission === 'access content' && $access_content,
    );
    return $account;
  }

  /**
   * Returns the value list of the forum_search_topic_grants condition.
   *
   * @return array|null
   *   The IN values, or NULL when the condition is absent.
   */
  private function grantCondition(Query $query): ?array {
    foreach ($this->conditions($query->getConditionGroup()) as [$field, $operator, $value]) {
      if ($field === 'forum_search_topic_grants' && $operator === 'IN') {
        return $value;
      }
    }
    return NULL;
  }

  /**
   * Flattens nested condition groups into field, operator and value tuples.
   *
   * @return array
   *   Flat condition list.
   */
  private function conditions(ConditionGroupInterface $group): array {
    $conditions = [];
    foreach ($group->getConditions() as $condition) {
      if ($condition instanceof ConditionGroupInterface) {
        $conditions = array_merge($conditions, $this->conditions($condition));
      }
      elseif ($condition instanceof ConditionInterface) {
        $conditions[] = [
          $condition->getField(),
          $condition->getOperator(),
          $condition->getValue(),
        ];
      }
    }
    return $conditions;
  }

}
