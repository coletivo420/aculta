<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests original entity access revalidation for catalog candidates.
 *
 * @group aculta_index
 */
final class ContentIndexAccessFilterTest extends UnitTestCase {

  /**
   * Tests only allowed current translations are returned with cache metadata.
   */
  public function testFiltersDeniedEntitiesAndMergesCacheability(): void {
    $account = $this->createMock(AccountInterface::class);
    $allowedTranslation = $this->translation(AccessResult::allowed()->addCacheContexts(['user.permissions']), ['node:1']);
    $deniedTranslation = $this->translation(AccessResult::forbidden()->addCacheContexts(['user.roles']), ['node:2']);
    $entities = [
      '1' => $this->sourceEntity($allowedTranslation),
      '2' => $this->sourceEntity($deniedTranslation),
    ];
    $manager = $this->entityManager($entities);

    $result = (new ContentIndexAccessFilter($manager))->filter([
      ['entity_type' => 'node', 'entity_id' => '1', 'langcode' => 'pt-br'],
      ['entity_type' => 'node', 'entity_id' => '2', 'langcode' => 'pt-br'],
    ], $account);

    self::assertSame([$allowedTranslation], $result->entities);
    self::assertContains('user.permissions', $result->cacheability->getCacheContexts());
    self::assertContains('user.roles', $result->cacheability->getCacheContexts());
    self::assertContains('node:1', $result->cacheability->getCacheTags());
    self::assertContains('node:2', $result->cacheability->getCacheTags());
  }

  /**
   * Tests a missing original entity fails closed and adds a list cache tag.
   */
  public function testMissingEntityIsDeniedAndListTagIsAdded(): void {
    $manager = $this->entityManager([]);
    $result = (new ContentIndexAccessFilter($manager))->filter([
      ['entity_type' => 'node', 'entity_id' => '404', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertSame([], $result->entities);
    self::assertContains('node_list', $result->cacheability->getCacheTags());
  }

  /**
   * Tests the access filter bounds candidates to one page.
   */
  public function testRejectsOversizedCandidatePage(): void {
    $candidates = array_fill(0, 101, [
      'entity_type' => 'node',
      'entity_id' => '1',
      'langcode' => 'pt-br',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    (new ContentIndexAccessFilter($this->createMock(EntityTypeManagerInterface::class)))
      ->filter($candidates, $this->createMock(AccountInterface::class));
  }

  /**
   * Creates a source entity mock with one translation.
   */
  private function sourceEntity(ContentEntityInterface $translation): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('hasTranslation')->with('pt-br')->willReturn(TRUE);
    $entity->method('getTranslation')->with('pt-br')->willReturn($translation);
    $entity->method('getCacheTags')->willReturn(['source_list']);
    return $entity;
  }

  /**
   * Creates a translated entity mock with a configured access result.
   *
   * @param \Drupal\Core\Access\AccessResult $access
   *   Result returned by the entity access handler.
   * @param list<string> $cacheTags
   *   Source cache tags.
   */
  private function translation(AccessResult $access, array $cacheTags): ContentEntityInterface {
    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->method('access')->with('view', self::anything(), TRUE)->willReturn($access);
    $translation->method('getCacheTags')->willReturn($cacheTags);
    return $translation;
  }

  /**
   * Creates the minimal entity type manager fixture.
   *
   * @param array<string, \Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Entities keyed by ID.
   */
  private function entityManager(array $entities): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($entities);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['node_list']);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $manager->method('getStorage')->with('node')->willReturn($storage);
    return $manager;
  }

}
