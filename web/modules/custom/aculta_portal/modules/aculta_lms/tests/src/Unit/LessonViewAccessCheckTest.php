<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\aculta_lms\Access\LessonViewAccessCheck;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests learner authorization for individual ACULTA lesson pages.
 */
#[CoversClass(LessonViewAccessCheck::class)]
#[Group('aculta_lms')]
final class LessonViewAccessCheckTest extends UnitTestCase {

  /**
   * Anonymous requests with a public slug are denied without type errors.
   */
  public function testAnonymousSlugIsDeniedBeforeConversion(): void {
    $group = $this->createMock(GroupInterface::class);
    $group->method('bundle')->willReturn(LmsFriendlySlugs::COURSE_BUNDLE);
    $group->method('isPublished')->willReturn(TRUE);
    $group->method('id')->willReturn('course-1');
    $group->method('getCacheContexts')->willReturn([]);
    $group->method('getCacheTags')->willReturn([]);
    $group->method('getCacheMaxAge')->willReturn(-1);

    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects(self::never())->method('getStorage');
    $slugs = new LmsFriendlySlugs(
      $entity_manager,
      new PhpTransliteration(NULL, $this->createMock(ModuleHandlerInterface::class)),
    );
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseEnrollmentCacheTags')->willReturn([
      'group_relationship_list:plugin:lms_classes:group:course-1',
      'group_relationship_list:plugin:group_membership:group:class-1',
    ]);
    $eligibility->expects(self::never())->method('isContentAvailable');
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('isAuthenticated')->willReturn(FALSE);

    $access = (new LessonViewAccessCheck($slugs, $eligibility, $user))
      ->access($group, 'introducao');

    self::assertTrue($access->isForbidden());
    self::assertContains(LmsFriendlySlugs::LESSON_LIST_CACHE_TAG, $access->getCacheTags());
    self::assertContains('group_relationship_list:plugin:group_membership:group:class-1', $access->getCacheTags());
  }

}
