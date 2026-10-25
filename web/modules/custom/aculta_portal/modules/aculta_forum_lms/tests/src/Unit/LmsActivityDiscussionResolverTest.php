<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_forum_lms\Context\LmsActivityDiscussionResolver;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\Lesson;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
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
    $activity = $this->createMock(Activity::class);
    $activity->method('id')->willReturn('18');
    $activity->method('isPublished')->willReturn(TRUE);
    $this->setCacheMetadata($activity, ['lms_activity:18']);
    $course = $this->createMock(CourseInterface::class);
    $course->method('id')->willReturn('4');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('getRelationships')->with('lms_classes')->willReturn([]);
    $this->setCacheMetadata($course, ['group:4']);
    $this->setCourseLessons($course, [$activity]);
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
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['group_relationship', $relationshipStorage],
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
    $activity = $this->createMock(Activity::class);
    $activity->method('isPublished')->willReturn(TRUE);
    $this->setCacheMetadata($activity, ['lms_activity:18']);
    $course = $this->createMock(CourseInterface::class);
    $course->method('getRelationships')->with('lms_classes')->willReturn([]);
    $this->setCacheMetadata($course, ['group:4']);
    $this->setCourseLessons($course, []);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
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
    $activity = $this->createMock(Activity::class);
    $activity->method('id')->willReturn('18');
    $activity->method('isPublished')->willReturn(TRUE);
    $this->setCacheMetadata($activity, ['lms_activity:18']);
    $course = $this->createMock(CourseInterface::class);
    $course->method('id')->willReturn('4');
    $course->method('getRelationships')->with('lms_classes')->willReturn([]);
    $this->setCacheMetadata($course, ['group:4']);
    $this->setCourseLessons($course, [$activity]);
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
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['group_relationship', $relationshipStorage],
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
   * Sets the published activity references exposed by a mock course. */
  private function setCourseLessons(CourseInterface $course, array $activities): void {
    $lesson = $this->createMock(Lesson::class);
    $lesson->method('isPublished')->willReturn(TRUE);
    $lesson->method('hasField')->with('activities')->willReturn(TRUE);
    $activityField = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $activityField->method('referencedEntities')->willReturn($activities);
    $lesson->method('get')->with('activities')->willReturn($activityField);
    $lessons = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $lessons->method('referencedEntities')->willReturn($activities === [] ? [] : [$lesson]);
    $course->method('get')->with(CourseInterface::LESSONS)->willReturn($lessons);
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
