<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\aculta_lms\Access\AnswerSubmissionAccessCheck;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(AnswerSubmissionAccessCheck::class)]
#[Group('aculta_lms')]
/**
 * Tests learner access to answer submission routes.
 */
final class AnswerSubmissionAccessCheckTest extends UnitTestCase {

  /**
   * Anonymous raw route slugs are denied before parameter conversion.
   */
  public function testAnonymousRawRouteSlugsAreDeniedWithoutTypeError(): void {
    $group = $this->createMock(GroupInterface::class);
    $group->method('bundle')->willReturn(LmsFriendlySlugs::COURSE_BUNDLE);
    $group->method('isPublished')->willReturn(TRUE);
    $group->method('id')->willReturn('88');
    $group->method('getCacheContexts')->willReturn([]);
    $group->method('getCacheTags')->willReturn([]);
    $group->method('getCacheMaxAge')->willReturn(-1);

    $slugs = new LmsFriendlySlugs(
      $this->createMock(EntityTypeManagerInterface::class),
      new PhpTransliteration(NULL, $this->createMock(ModuleHandlerInterface::class)),
    );
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('isAuthenticated')->willReturn(FALSE);

    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseEnrollmentCacheTags')->willReturn([
      'group_relationship_list:plugin:lms_classes:group:88',
      'group_relationship_list:plugin:group_membership:group:89',
    ]);
    $result = (new AnswerSubmissionAccessCheck(
      $slugs,
      $eligibility,
      $currentUser,
    ))->access($group, 'aula-1', 'true-false');

    self::assertTrue($result->isForbidden());
    self::assertContains(LmsFriendlySlugs::LESSON_LIST_CACHE_TAG, $result->getCacheTags());
    self::assertContains(LmsFriendlySlugs::ACTIVITY_LIST_CACHE_TAG, $result->getCacheTags());
    self::assertContains('group_relationship_list:plugin:group_membership:group:89', $result->getCacheTags());
  }

}
