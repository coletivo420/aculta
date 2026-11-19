<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests fail-closed dashboard behavior for groups outside the LMS.
 */
#[CoversClass(LearningDashboardController::class)]
#[Group('aculta_lms')]
final class LearningDashboardControllerTest extends UnitTestCase {

  /**
   * Authorized dashboard metrics and period selection preserve their contracts.
   */
  public function testAuthorizedDashboardRendersMetricsAndCurrentPeriodLink(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn('7');
    $account->method('hasPermission')->with('administer lms')->willReturn(FALSE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('1');
    $course->method('label')->willReturn(NULL);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->with('grade students', $account)->willReturn(TRUE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $class = $this->createMock(GroupInterface::class);
    $class->method('id')->willReturn('2');
    $class->method('bundle')->willReturn('lms_class');
    $class->method('getCacheContexts')->willReturn([]);
    $class->method('getCacheTags')->willReturn([]);
    $class->method('getCacheMaxAge')->willReturn(-1);

    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getGroup')->willReturn($course);
    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->expects(self::exactly(2))->method('loadByProperties')->willReturn([$relationship]);
    $relationshipStorage->method('loadMultiple')->willReturn([]);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $relationshipStorage->method('getQuery')->willReturn($query);
    $membershipCache = $this->createMock(CacheBackendInterface::class);
    $membershipCache->method('get')->willReturn(FALSE);

    $lessonStorage = $this->createMock(EntityStorageInterface::class);
    $lessonStorage->expects(self::once())->method('loadByProperties')->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(
      static fn(string $type): EntityStorageInterface => match ($type) {
        'group_relationship' => $relationshipStorage,
        'aculta_lms_lesson' => $lessonStorage,
        default => throw new \LogicException(sprintf('Unexpected storage "%s".', $type)),
      },
    );
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('cache.group_memberships_chained', $membershipCache);
    \Drupal::setContainer($container);

    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->method('loadForStudent')->with(7)->willReturn([]);
    $dashboards = new LearningDashboardManager(
      NULL,
      $certificates,
      $entityTypeManager,
      $this->createMock(CourseProgressReaderInterface::class),
      $this->createMock(CourseLessonReaderInterface::class),
    );
    $deadlines = new ClassActivityDeadlineManager(
      $entityTypeManager,
      new ClassActivityDeadlineAccessCheck($entityTypeManager, $account),
    );
    $controller = new LearningDashboardController(
      $dashboards,
      $deadlines,
      $entityTypeManager,
      $account,
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );

    try {
      $build = $controller->class($class, $course);
      self::assertIsArray($build['course'] ?? NULL);
      $courseRenderArray = $build['course'];
      self::assertIsString($courseRenderArray['#value'] ?? NULL);
      self::assertSame('Curso: ', $courseRenderArray['#value']);
      self::assertIsArray($build['metrics'] ?? NULL);
      $metrics = $build['metrics'];
      self::assertIsArray($metrics['#rows'] ?? NULL);
      $metricRows = $metrics['#rows'];
      self::assertCount(6, $metricRows);
      self::assertSame([0, 0, 0, 0, 0, 0], array_column($metricRows, 1));
      self::assertIsArray($build['status_history'] ?? NULL);
      $history = $build['status_history'];
      self::assertIsArray($history['period'] ?? NULL);
      $period = $history['period'];
      self::assertIsArray($period['#slots'] ?? NULL);
      $slots = $period['#slots'];
      self::assertIsArray($slots['items'] ?? NULL);
      $items = $slots['items'];
      self::assertIsArray($items['#items'] ?? NULL);
      $links = $items['#items'];
      self::assertCount(3, $links);
      self::assertIsArray($links[0] ?? NULL);
      $firstLink = $links[0];
      self::assertIsArray($firstLink['#attributes'] ?? NULL);
      $attributes = $firstLink['#attributes'];
      self::assertSame(['is-active'], $attributes['class'] ?? NULL);
      self::assertSame('page', $attributes['aria-current'] ?? NULL);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * A non-LMS Group never renders dashboard content.
   */
  public function testDashboardRejectsGroupOutsideLms(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->method('loadByProperties')->willReturn([]);
    $entityTypeManager->method('getStorage')->with('group_relationship')->willReturn($relationshipStorage);
    $dashboards = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $entityTypeManager,
      $this->createMock(CourseProgressReaderInterface::class),
      $this->createMock(CourseLessonReaderInterface::class),
    );
    $deadlines = new ClassActivityDeadlineManager(
      $entityTypeManager,
      new ClassActivityDeadlineAccessCheck($entityTypeManager, $account),
    );
    $controller = new LearningDashboardController(
      $dashboards,
      $deadlines,
      $entityTypeManager,
      $account,
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );
    $group = $this->createMock(GroupInterface::class);
    $group->method('bundle')->willReturn('not_an_lms_class');
    $group->method('id')->willReturn('2');
    $group->method('getCacheContexts')->willReturn([]);
    $group->method('getCacheTags')->willReturn([]);
    $group->method('getCacheMaxAge')->willReturn(-1);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('1');

    $build = $controller->class($group, $course);
    self::assertArrayHasKey('#plain_text', $build);
    self::assertInstanceOf(TranslatableMarkup::class, $build['#plain_text']);
    self::assertSame('Turma indisponível.', (string) $build['#plain_text']);
  }

}
