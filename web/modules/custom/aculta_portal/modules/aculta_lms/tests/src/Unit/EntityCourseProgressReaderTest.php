<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\EntityCourseProgressReader;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('aculta_lms')]
final class EntityCourseProgressReaderTest extends UnitTestCase {

  public function testReadsCurrentProgressAndConvertsScoreToFloat(): void {
    $entity = $this->progressEntity('passed', TRUE, '82.5', ['aculta_lms_progress:12']);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['course_id' => 31, 'uid' => 7])
      ->willReturn([$entity]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_lms_progress')->willReturn($storage);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('31');
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('7');

    $progress = (new EntityCourseProgressReader($manager))->current($course, $account);

    self::assertInstanceOf(CourseProgress::class, $progress);
    self::assertSame(CourseProgress::STATUS_PASSED, $progress->status);
    self::assertTrue($progress->finished);
    self::assertSame(82.5, $progress->score);
    self::assertSame(['aculta_lms_progress:12'], $progress->cacheTags);
    self::assertSame(123, $progress->changedTime);
  }

  public function testReturnsNullScoreAndMissingProgress(): void {
    $entity = $this->progressEntity(CourseProgress::STATUS_PROGRESS, FALSE, NULL);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturnOnConsecutiveCalls([$entity], []);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_progress')->willReturn($storage);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn(31);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $reader = new EntityCourseProgressReader($manager);

    self::assertNull($reader->current($course, $account)?->score);
    self::assertNull($reader->current($course, $account));
  }

  private function progressEntity(string $status, bool $finished, mixed $score, array $cacheTags = []): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('get')->willReturnCallback(function (string $field) use ($status, $finished, $score): FieldItemListInterface {
      $value = match ($field) {
        'status' => $status,
        'finished' => $finished,
        'score' => $score,
        'changed' => 123,
      };
      $field_list = $this->createMock(FieldItemListInterface::class);
      $field_list->method('getValue')->willReturn($value === NULL ? [] : [['value' => $value]]);
      return $field_list;
    });
    $entity->method('getCacheTags')->willReturn($cacheTags);
    return $entity;
  }

}
