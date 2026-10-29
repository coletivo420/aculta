<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\Cache;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 */
#[CoversClass(aculta_H5PCourseAccessManager::class)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
final class aculta_H5PCourseAccessManagerTest extends UnitTestCase {

  /**
   * Restricts student playback to content referenced by their course. */
  public function testStudentCanAccessOnlyContentReferencedByTheirCourse(): void {
    [$manager] = $this->managerForCourse([41]);
    self::assertTrue($manager->studentAccess(8, 41, $this->createMock(AccountInterface::class))->isAllowed());
    self::assertFalse($manager->studentAccess(8, 42, $this->createMock(AccountInterface::class))->isAllowed());
  }

  /**
   * Includes course and H5P content cache metadata in access results. */
  public function testStudentAccessIncludesGroupAndContentCacheability(): void {
    [$manager] = $this->managerForCourse([41]);
    $result = $manager->studentAccess(8, 41, $this->createMock(AccountInterface::class));
    self::assertContains('user', $result->getCacheContexts());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
    self::assertContains('group:8', $result->getCacheTags());
    self::assertContains('aculta_h5p_content:41', $result->getCacheTags());
  }

  /**
   * Requires Group update access and an existing course reference to edit. */
  public function testEditorNeedsGroupUpdateAccessAndExistingReference(): void {
    [$manager] = $this->managerForCourse([41], FALSE);
    self::assertFalse($manager->editorAccess(8, 41, $this->createMock(AccountInterface::class))->isAllowed());
  }

  /**
   * Creates the course/content storage boundary used by the access manager.
   *
   * @param int[] $contentIds
   *   H5P content IDs linked from the mocked course.
   * @param bool $allowed
   *   Whether the Group permits the requested access operation.
   *
   * @return array{0: \Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager, 1: \Drupal\lms\Entity\Bundle\CourseInterface}
   *   The access manager and its mocked course.
   */
  private function managerForCourse(array $contentIds, bool $allowed = TRUE): array {
    $course = $this->createMock(CourseInterface::class);
    $course->method('id')->willReturn(8);
    $course->method('getCacheTags')->willReturn(['group:8']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $course->method('hasField')->with('field_h5p_activities')->willReturn(TRUE);
    $items = array_map(static fn (int $id): object => (object) ['h5p_content_id' => $id], $contentIds);
    $field = $this->createMock(FieldItemList::class);
    $field->method('getIterator')->willReturn(new \ArrayIterator($items));
    $course->method('get')->with('field_h5p_activities')->willReturn($field);
    $course->method('takeAccess')->willReturn(AccessResult::allowedIf($allowed));
    $course->method('access')->with('update', self::anything(), TRUE)->willReturn(AccessResult::allowedIf($allowed));

    $groupStorage = $this->createMock(EntityStorageInterface::class);
    $groupStorage->method('load')->with(8)->willReturn($course);
    $contentStorage = $this->createMock(EntityStorageInterface::class);
    $content = $this->createMock(CacheableDependencyInterface::class);
    $content->method('getCacheTags')->willReturn(['aculta_h5p_content:41']);
    $content->method('getCacheContexts')->willReturn([]);
    $content->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $contentStorage->method('load')->willReturnCallback(static fn (int $id) => $id === 41 ? $content : NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $type) => match ($type) {
      'group' => $groupStorage,
      'aculta_h5p_content' => $contentStorage,
    });
    return [new aculta_H5PCourseAccessManager($entityTypeManager), $course];
  }

}
