<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\PublicForumListViewHooks;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Tests the owner module's public topic-list preparation. */
#[Group('aculta_forum')]
final class PublicForumListViewHooksTest extends UnitTestCase {

  /**
   * Public counts remain access-checked and carry list cache metadata.
   */
  public function testPublicTopicCountsAreAccessCheckedAndCacheable(): void {
    $query = $this->createMock(QueryInterface::class);
    $conditions = [];
    $query->expects(self::once())
      ->method('accessCheck')
      ->with(TRUE)
      ->willReturnSelf();
    $query->expects(self::exactly(2))
      ->method('condition')
      ->willReturnCallback(static function (string $field, mixed $value) use (&$conditions, $query): QueryInterface {
        $conditions[] = [$field, $value];
        return $query;
      });
    $query->expects(self::once())
      ->method('count')
      ->willReturnSelf();
    $query->expects(self::once())
      ->method('execute')
      ->willReturn(17);

    $nodeStorage = new PublicForumListTestEntityStorage($query);
    $termStorage = new PublicForumListTestEntityStorage($query);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['taxonomy_term', $termStorage],
    ]);
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn('aculta_forum_public_topics');

    $variables = ['view' => $view];
    (new PublicForumListViewHooks($entityTypeManager))->preprocessViewsView($variables);

    self::assertSame([], $variables['forum_categories']);
    self::assertSame(17, $variables['forum_total_topics']);
    self::assertSame([['type', 'aculta_forum_topic'], ['status', 1]], $conditions);
    self::assertContains('user.node_grants:view', $variables['forum_list_cache_metadata']['#cache']['contexts']);
    self::assertContains('node_list:aculta_forum_topic', $variables['forum_list_cache_metadata']['#cache']['tags']);
  }

  /**
   * Unrelated Views do not load forum data.
   */
  public function testOtherViewsAreIgnored(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn('aculta_forum_search');
    $variables = ['view' => $view];

    (new PublicForumListViewHooks($entityTypeManager))->preprocessViewsView($variables);

    self::assertArrayNotHasKey('forum_categories', $variables);
  }

}

/**
 * Minimal entity storage double for the public-list hook unit test.
 */
final class PublicForumListTestEntityStorage extends EntityStorageBase {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    throw new \LogicException('This test double is never container-created.');
  }

  /**
   * Constructs the test storage.
   */
  public function __construct(
    protected readonly QueryInterface $query,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getQuery($conjunction = 'AND') {
    return $this->query;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = []) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities): void {}

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'test';
  }

}
