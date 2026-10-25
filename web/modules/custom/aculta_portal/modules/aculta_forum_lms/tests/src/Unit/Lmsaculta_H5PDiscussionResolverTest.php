<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_forum_lms\Context\Lmsaculta_H5PDiscussionResolver;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests H5P discussion resolution against the current course. */
#[CoversClass(Lmsaculta_H5PDiscussionResolver::class)]
#[Group('aculta_forum_lms')]
final class Lmsaculta_H5PDiscussionResolverTest extends UnitTestCase {

  /**
   * A topic appears only when linked to the target H5P content and allowed. */
  public function testBuildIncludesOnlyAuthorizedH5pTopic(): void {
    $student = $this->createMock(UserInterface::class);
    $course = $this->courseMock();
    $course->method('id')->willReturn('12');
    $course->method('hasField')->with('field_h5p_activities')->willReturn(TRUE);
    $course->method('get')->with('field_h5p_activities')->willReturn([(object) ['h5p_content_id' => 34]]);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_lms_topic');
    $topic->method('label')->willReturn('Conversa da atividade');
    $topic->method('toUrl')->willReturn($this->createMock(Url::class));
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheTags')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);
    $topic->method('access')->with('view', $student, TRUE)->willReturn(AccessResult::allowed());

    $otherCourse = $this->createMock(GroupInterface::class);
    $otherCourse->method('id')->willReturn('13');
    $otherCourse->method('bundle')->willReturn('lms_course');
    $otherCourse->method('getCacheContexts')->willReturn([]);
    $otherCourse->method('getCacheTags')->willReturn([]);
    $otherCourse->method('getCacheMaxAge')->willReturn(-1);
    $foreignTopic = $this->createMock(NodeInterface::class);
    $foreignTopic->method('bundle')->willReturn('aculta_lms_topic');
    $foreignTopic->method('getCacheContexts')->willReturn([]);
    $foreignTopic->method('getCacheTags')->willReturn([]);
    $foreignTopic->method('getCacheMaxAge')->willReturn(-1);
    $foreignTopic->method('access')->with('view', $student, TRUE)->willReturn(AccessResult::allowed());

    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getGroup')->willReturn($course);
    $relationship->method('getCacheContexts')->willReturn([]);
    $relationship->method('getCacheTags')->willReturn([]);
    $relationship->method('getCacheMaxAge')->willReturn(-1);
    $foreignRelationship = $this->createMock(GroupRelationshipInterface::class);
    $foreignRelationship->method('getGroup')->willReturn($otherCourse);
    $foreignRelationship->method('getCacheContexts')->willReturn([]);
    $foreignRelationship->method('getCacheTags')->willReturn([]);
    $foreignRelationship->method('getCacheMaxAge')->willReturn(-1);
    $relationshipStorage = $this->createMock(GroupRelationshipStorageInterface::class);
    $relationshipStorage->method('loadByEntity')->willReturnCallback(
      fn (NodeInterface $entity, string $pluginId): array => $pluginId === 'group_node:aculta_lms_topic'
        ? ($entity === $topic ? [$relationship] : [$foreignRelationship])
        : [],
    );

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([56, 57]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([56, 57])->willReturn([56 => $topic, 57 => $foreignTopic]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $type) => match ($type) {
      'node' => $nodeStorage,
      'group_relationship' => $relationshipStorage,
      default => throw new \UnexpectedValueException($type),
    });

    $resolver = new Lmsaculta_H5PDiscussionResolver(
      $entityTypeManager,
      $this->createMock(TranslationInterface::class),
    );
    $build = $resolver->build(34, $course, $student);

    self::assertCount(1, $build['links']['#items']);
    self::assertSame('Conversa da atividade', $build['links']['#items'][0]['#title']);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
  }

  /**
   * A discussion is suppressed if the course does not own the H5P content. */
  public function testBuildFailsClosedForUnlinkedH5pContent(): void {
    $student = $this->createMock(UserInterface::class);
    $course = $this->courseMock();
    $course->method('hasField')->with('field_h5p_activities')->willReturn(TRUE);
    $course->method('get')->with('field_h5p_activities')->willReturn([(object) ['h5p_content_id' => 35]]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');

    $resolver = new Lmsaculta_H5PDiscussionResolver(
      $entityTypeManager,
      $this->createMock(TranslationInterface::class),
    );

    self::assertSame('', $resolver->build(34, $course, $student)['#markup']);
  }

  /**
   * Creates a cacheable course entity mock.
   */
  private function courseMock(): CourseInterface {
    $course = $this->createMock(CourseInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('12');
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return $course;
  }

}
