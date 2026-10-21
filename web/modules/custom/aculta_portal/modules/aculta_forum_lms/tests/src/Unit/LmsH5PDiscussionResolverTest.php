<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_forum_lms\Context\LmsH5PDiscussionResolver;
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
#[CoversClass(LmsH5PDiscussionResolver::class)]
#[Group('aculta_forum_lms')]
final class LmsH5PDiscussionResolverTest extends UnitTestCase {

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

    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getGroup')->willReturn($course);
    $relationship->method('getCacheContexts')->willReturn([]);
    $relationship->method('getCacheTags')->willReturn([]);
    $relationship->method('getCacheMaxAge')->willReturn(-1);
    $relationshipStorage = $this->createMock(GroupRelationshipStorageInterface::class);
    $relationshipStorage->method('loadByEntity')->with($topic, 'group_node:aculta_lms_topic')->willReturn([$relationship]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([56]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([56])->willReturn([56 => $topic]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $type) => match ($type) {
      'node' => $nodeStorage,
      'group_relationship' => $relationshipStorage,
      default => throw new \UnexpectedValueException($type),
    });

    $resolver = new LmsH5PDiscussionResolver(
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

    $resolver = new LmsH5PDiscussionResolver(
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
