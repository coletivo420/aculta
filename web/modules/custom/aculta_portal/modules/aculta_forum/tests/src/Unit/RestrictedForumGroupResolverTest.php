<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolver;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests that Group relationships are queried per content plugin.
 */
#[Group('aculta_forum')]
final class RestrictedForumGroupResolverTest extends UnitTestCase {

  /**
   * Node IDs and term IDs with the same number do not cross-match.
   */
  public function testGroupIdsAreResolvedPerContentPlugin(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('id')->willReturn(123);
    $topic->method('hasField')->with('field_aculta_categories')->willReturn(TRUE);
    $categories = $this->createMock(FieldItemListInterface::class);
    $categories->method('getValue')->willReturn([['target_id' => '456']]);
    $topic->method('get')->with('field_aculta_categories')->willReturn($categories);

    $topicConditions = [];
    $categoryConditions = [];
    $topicQuery = $this->query($topicConditions);
    $categoryQuery = $this->query($categoryConditions);

    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->method('getQuery')
      ->willReturnOnConsecutiveCalls($topicQuery, $categoryQuery);
    $relationshipStorage->method('loadMultiple')->willReturn([]);

    $groupTypeStorage = $this->createMock(EntityStorageInterface::class);
    $groupTypeStorage->method('load')->with('forum_associados')
      ->willReturn($this->createMock(GroupTypeInterface::class));

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['group_type', $groupTypeStorage],
      ['group_relationship', $relationshipStorage],
    ]);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('restricted_group_type')->willReturn('forum_associados');
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $resolver = new RestrictedForumGroupResolver(
      $configFactory,
      $entityTypeManager,
      $this->createMock(LoggerInterface::class),
    );

    self::assertSame([], $resolver->groupIdsForTopic($topic));
    self::assertSame([
      ['plugin_id', RestrictedForumGroupResolver::TOPIC_PLUGIN, '='],
      ['entity_id', [123], 'IN'],
    ], $topicConditions);
    self::assertSame([
      ['plugin_id', RestrictedForumGroupResolver::CATEGORY_PLUGIN, '='],
      ['entity_id', [456], 'IN'],
    ], $categoryConditions);
  }

  /**
   * Creates a query double that records the conditions it receives.
   *
   * @param array<int, array{0: string, 1: mixed, 2: string}> $conditions
   *   Captured conditions, by reference.
   */
  private function query(array &$conditions): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnCallback(
      static function (string $field, mixed $value, ?string $operator = NULL) use (&$conditions, $query): QueryInterface {
        $conditions[] = [$field, $value, $operator ?? '='];
        return $query;
      },
    );
    $query->method('execute')->willReturn([]);
    return $query;
  }

}
