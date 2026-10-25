<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolver;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the Group adapter that decides which topics are restricted.
 */
#[CoversClass(RestrictedForumGroupResolver::class)]
final class RestrictedForumGroupResolverTest extends TestCase {

  /**
   * Empty configuration means no restricted groups.
   */
  public function testEmptyConfigurationMeansNoGroups(): void {
    $resolver = $this->resolver('');
    self::assertNull($resolver->configuredGroupType());
    self::assertSame([], $resolver->groupsOfType());
    self::assertSame([], $resolver->groupIdsForTopic($this->topic()));
  }

  /**
   * Blank configuration is trimmed to no configuration.
   */
  public function testBlankConfigurationIsTrimmedToNone(): void {
    self::assertNull($this->resolver('   ')->configuredGroupType());
  }

  /**
   * LMS group types are never accepted for restricted forums.
   */
  public function testLmsGroupTypeIsRefused(): void {
    $logger = $this->createMock(LoggerInterface::class);
    // Uma chamada por configuredGroupType(): direta e dentro de groupsOfType().
    $logger->expects(self::exactly(2))->method('error');
    $resolver = $this->resolver('lms_course', logger: $logger);
    self::assertNull($resolver->configuredGroupType());
    self::assertSame([], $resolver->groupsOfType());
  }

  /**
   * LMS class type is refused as well.
   */
  public function testLmsClassTypeIsRefused(): void {
    self::assertNull($this->resolver('lms_class')->configuredGroupType());
  }

  /**
   * A configured type that does not exist is ignored with a warning.
   */
  public function testUnknownGroupTypeIsIgnored(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('warning');
    $resolver = $this->resolver('forum_associados', exists: FALSE, logger: $logger);
    self::assertNull($resolver->configuredGroupType());
  }

  /**
   * A valid configured type is returned as is.
   */
  public function testValidGroupTypeIsReturned(): void {
    self::assertSame('forum_associados', $this->resolver('forum_associados')->configuredGroupType());
  }

  /**
   * Non topic bundles are never restricted.
   */
  public function testNonTopicBundleIsNeverRestricted(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('page');
    $groupTypeStorage = $this->createMock(EntityStorageInterface::class);
    $groupTypeStorage->method('load')->willReturn($this->createMock(GroupTypeInterface::class));
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    // Só o tipo configurado é consultado: nenhuma relação é lida.
    $manager->expects(self::once())->method('getStorage')->with('group_type')->willReturn($groupTypeStorage);
    $resolver = new RestrictedForumGroupResolver(
      $this->config('forum_associados'),
      $manager,
      $this->createMock(LoggerInterface::class),
    );
    self::assertSame([], $resolver->groupIdsForTopic($topic));
  }

  /**
   * No relationship rows means the topic is not restricted.
   */
  public function testTopicWithoutRelationshipsIsNotRestricted(): void {
    self::assertSame([], $this->resolver('forum_associados')->groupIdsForTopic($this->topic()));
  }

  /**
   * Groups of the configured type are returned keyed by id.
   */
  public function testGroupsOfTypeAreReturnedKeyedById(): void {
    $groups = [4 => $this->group(4), 9 => $this->group(9)];
    $resolver = $this->resolver('forum_associados', groups: $groups);
    self::assertSame($groups, $resolver->groupsOfType());
  }

  /**
   * Loaded entities that are not groups are dropped.
   */
  public function testNonGroupEntitiesAreDropped(): void {
    $resolver = $this->resolver('forum_associados', groups: [4 => $this->group(4), 7 => NULL]);
    self::assertSame([4], array_keys($resolver->groupsOfType()));
  }

  /**
   * Builds the resolver with stubbed configuration, storage and logger.
   *
   * @param string $configuredType
   *   Valor de `aculta_forum.settings.restricted_group_type`.
   * @param bool $exists
   *   Se o tipo de Group existe.
   * @param array<int, \Drupal\group\Entity\GroupInterface|null> $groups
   *   Groups devolvidos pela consulta, por id.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   Logger a injetar.
   */
  private function resolver(string $configuredType, bool $exists = TRUE, array $groups = [], ?LoggerInterface $logger = NULL): RestrictedForumGroupResolver {
    return new RestrictedForumGroupResolver(
      $this->config($configuredType),
      $this->entityTypeManager($exists, $groups),
      $logger ?? $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Returns a config factory reporting the given restricted group type.
   */
  private function config(string $configuredType): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('restricted_group_type')->willReturn($configuredType);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_forum.settings')->willReturn($config);
    return $factory;
  }

  /**
   * Returns an entity type manager answering the resolver queries.
   *
   * @param bool $exists
   *   Se o tipo de Group existe.
   * @param array<int, \Drupal\group\Entity\GroupInterface|null> $groups
   *   Groups devolvidos pela consulta, por id.
   */
  private function entityTypeManager(bool $exists, array $groups): EntityTypeManagerInterface {
    $groupTypeStorage = $this->createMock(EntityStorageInterface::class);
    $groupTypeStorage->method('load')->willReturn($exists ? $this->createMock(GroupTypeInterface::class) : NULL);

    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->method('getQuery')->willReturn($this->query([]));

    $groupStorage = $this->createMock(EntityStorageInterface::class);
    $groupStorage->method('getQuery')->willReturn($this->query(array_keys($groups)));
    $groupStorage->method('loadMultiple')->willReturn(array_filter($groups));

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['group_type', $groupTypeStorage],
      ['group_relationship', $relationshipStorage],
      ['group', $groupStorage],
    ]);
    return $manager;
  }

  /**
   * Returns a query fixture that resolves to the given ids.
   */
  private function query(array $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    return $query;
  }

  /**
   * Returns a group fixture of the restricted type.
   */
  private function group(int $gid): GroupInterface {
    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn($gid);
    $group->method('bundle')->willReturn('forum_associados');
    return $group;
  }

  /**
   * Returns a forum topic fixture without relationships.
   */
  private function topic(int $nid = 11): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn($nid);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->willReturn(FALSE);
    return $topic;
  }

}
