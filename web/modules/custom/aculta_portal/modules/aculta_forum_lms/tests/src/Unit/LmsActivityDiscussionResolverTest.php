<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_forum_lms\Context\LmsActivityDiscussionResolver;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies activity discussions stay inside the course and Group access.
 */
#[Group('aculta_forum_lms')]
final class LmsActivityDiscussionResolverTest extends UnitTestCase {

  /**
   * Links an authorized topic in the current course.
   */
  public function testOnlyAccessibleDiscussionInSameCourseIsLinked(): void {
    $activity = $this->activity();
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('4');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('getRelationships')->with('lms_classes')->willReturn([]);
    $this->setCacheMetadata($course, ['group:4']);
    $account = $this->createMock(UserInterface::class);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_lms_topic');
    $topic->method('label')->willReturn('Perguntas da atividade');
    $topic->method('toUrl')->willReturn(Url::fromRoute('entity.node.canonical', ['node' => 29]));
    $topic->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $topic->method('getCacheTags')->willReturn(['node:29']);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);

    $hiddenTopic = $this->createMock(NodeInterface::class);
    $hiddenTopic->method('bundle')->willReturn('aculta_lms_topic');
    $hiddenTopic->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::forbidden());
    $this->setCacheMetadata($hiddenTopic, ['node:30']);

    $activityQuery = $this->createMock(QueryInterface::class);
    $activityQuery->method('accessCheck')->with(FALSE)->willReturnSelf();
    $activityQuery->method('condition')->willReturnSelf();
    $activityQuery->method('sort')->willReturnSelf();
    $activityQuery->method('execute')->willReturn([29, 30]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($activityQuery);
    $nodeStorage->method('loadMultiple')->with([29, 30])->willReturn([$topic, $hiddenTopic]);

    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn('4');
    $group->method('bundle')->willReturn('lms_course');
    $group->method('getCacheTags')->willReturn(['group:4']);
    $group->method('getCacheContexts')->willReturn([]);
    $group->method('getCacheMaxAge')->willReturn(-1);
    $topicRelationship = $this->createMock(GroupRelationshipInterface::class);
    $topicRelationship->method('getGroup')->willReturn($group);
    $topicRelationship->method('getCacheTags')->willReturn(['group_relationship:7']);
    $topicRelationship->method('getCacheContexts')->willReturn([]);
    $topicRelationship->method('getCacheMaxAge')->willReturn(-1);
    $relationshipStorage = $this->createMock(GroupRelationshipStorageInterface::class);
    $relationshipStorage->method('loadByEntity')
      ->with(self::isInstanceOf(NodeInterface::class), 'group_node:aculta_lms_topic')
      ->willReturn([$topicRelationship]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $lessonStorage = $this->createMock(EntityStorageInterface::class);
    $lessonStorage->method('load')->with(11)->willReturn($this->lesson(4));
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['group_relationship', $relationshipStorage],
      ['aculta_lms_lesson', $lessonStorage],
    ]);
    $activityCheck = new ClassActivityDeadlineAccessCheck($entityTypeManager, $this->createMock(AccountProxyInterface::class));
    $resolver = new LmsActivityDiscussionResolver(
      $entityTypeManager,
      new ClassActivityDeadlineManager($entityTypeManager, $activityCheck),
      $this->translation(),
    );

    $build = $resolver->build($activity, $course, $account);

    self::assertSame('lms-activity-discussions', $build['#attributes']['data-portal-ui']);
    self::assertCount(1, $build['links']['#items']);
    self::assertSame('Perguntas da atividade', $build['links']['#items'][0]['#title']);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
    self::assertContains('group_relationship_list:plugin:group_node:aculta_lms_topic', $build['#cache']['tags']);
  }

  /**
   * Omits links when the activity does not belong to the course.
   */
  public function testActivityOutsideCourseReturnsNoDiscussion(): void {
    $activity = $this->activity();
    $course = $this->createMock(GroupInterface::class);
    $course->method('getRelationships')->with('lms_classes')->willReturn([]);
    $this->setCacheMetadata($course, ['group:4']);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $lessonStorage = $this->createMock(EntityStorageInterface::class);
    $lessonStorage->method('load')->with(11)->willReturn($this->lesson(5));
    $entityTypeManager->method('getStorage')->with('aculta_lms_lesson')->willReturn($lessonStorage);
    $accessCheck = new ClassActivityDeadlineAccessCheck($entityTypeManager, $this->createMock(AccountProxyInterface::class));
    $resolver = new LmsActivityDiscussionResolver(
      $entityTypeManager,
      new ClassActivityDeadlineManager($entityTypeManager, $accessCheck),
      $this->translation(),
    );

    $build = $resolver->build($activity, $course, $this->createMock(UserInterface::class));

    self::assertArrayNotHasKey('links', $build);
    self::assertSame('', $build['#markup']);
  }

  /**
   * Links class discussions only through their unique parent course.
   */
  public function testDiscussionInClassUnderCourseIsLinked(): void {
    $activity = $this->activity();
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('4');
    $course->method('getRelationships')->with('lms_classes')->willReturn([]);
    $this->setCacheMetadata($course, ['group:4']);
    $account = $this->createMock(UserInterface::class);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_lms_topic');
    $topic->method('label')->willReturn('Perguntas da turma');
    $topic->method('toUrl')->willReturn(Url::fromRoute('entity.node.canonical', ['node' => 30]));
    $topic->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $this->setCacheMetadata($topic, ['node:30']);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([30]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([30])->willReturn([$topic]);

    $class = $this->createMock(GroupInterface::class);
    $class->method('id')->willReturn('7');
    $class->method('bundle')->willReturn('lms_class');
    $this->setCacheMetadata($class, ['group:7']);
    $classCourseRelation = $this->createMock(GroupRelationshipInterface::class);
    $classCourseRelation->method('getGroup')->willReturn($course);
    $classCourseRelation->method('getEntity')->willReturn($class);
    $this->setCacheMetadata($classCourseRelation, ['group_relationship:8']);
    $topicRelation = $this->createMock(GroupRelationshipInterface::class);
    $topicRelation->method('getGroup')->willReturn($class);
    $this->setCacheMetadata($topicRelation, ['group_relationship:9']);
    $relationshipStorage = $this->createMock(GroupRelationshipStorageInterface::class);
    $relationshipStorage->method('loadByEntity')->willReturnCallback(
      static fn (object $entity, string $plugin): array => match ([$entity, $plugin]) {
        [$topic, 'group_node:aculta_lms_topic'] => [$topicRelation],
        [$class, 'lms_classes'] => [$classCourseRelation],
        default => [],
      },
    );
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $lessonStorage = $this->createMock(EntityStorageInterface::class);
    $lessonStorage->method('load')->with(11)->willReturn($this->lesson(4));
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['group_relationship', $relationshipStorage],
      ['aculta_lms_lesson', $lessonStorage],
    ]);
    $resolver = new LmsActivityDiscussionResolver(
      $entityTypeManager,
      new ClassActivityDeadlineManager(
        $entityTypeManager,
        new ClassActivityDeadlineAccessCheck($entityTypeManager, $this->createMock(AccountProxyInterface::class)),
      ),
      $this->translation(),
    );

    $build = $resolver->build($activity, $course, $account);

    self::assertCount(1, $build['links']['#items']);
    self::assertSame('Perguntas da turma', $build['links']['#items'][0]['#title']);
    self::assertContains('group_relationship_list:plugin:lms_classes:entity:7', $build['#cache']['tags']);
  }

  /**
   * Creates an ACULTA activity linked to lesson 11. */
  private function activity(): ContentEntityInterface {
    $activity = $this->createMock(ContentEntityInterface::class);
    $activity->method('getEntityTypeId')->willReturn('aculta_lms_activity');
    $activity->method('id')->willReturn('18');
    $activity->method('hasField')->with('lesson_id')->willReturn(TRUE);
    $activity->method('get')->with('lesson_id')->willReturn($this->fieldList(11));
    $this->setCacheMetadata($activity, ['aculta_lms_activity:18']);
    return $activity;
  }

  /**
   * Creates a course lesson with publication and course fields. */
  private function lesson(int $courseId): ContentEntityInterface {
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('hasField')->willReturnMap([['published', TRUE], ['course_id', TRUE]]);
    $lesson->method('get')->willReturnMap([
      ['published', $this->fieldList(TRUE)],
      ['course_id', $this->fieldList($courseId)],
    ]);
    return $lesson;
  }

  /**
   * Creates a field item list with one value. */
  private function fieldList(mixed $value): FieldItemListInterface {
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getValue')->willReturn(['value' => $value]);
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('first')->willReturn($item);
    return $list;
  }

  /**
   * Creates a translator returning renderable text. */
  private function translation(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn(new TranslatableMarkup('Discussões desta atividade'));
    return $translation;
  }

  /**
   * Configures cache metadata on a cacheable mock entity. */
  private function setCacheMetadata(object $entity, array $tags): void {
    $entity->method('getCacheTags')->willReturn($tags);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
