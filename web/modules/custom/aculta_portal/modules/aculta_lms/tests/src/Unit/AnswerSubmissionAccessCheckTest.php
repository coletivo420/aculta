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

/** @coversDefaultClass \Drupal\aculta_lms\Access\AnswerSubmissionAccessCheck */
final class AnswerSubmissionAccessCheckTest extends UnitTestCase {

  /** @covers ::access */
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

    $result = (new AnswerSubmissionAccessCheck(
      $slugs,
      $this->createMock(LearningEligibilityInterface::class),
      $currentUser,
    ))->access($group, 'aula-1', 'true-false');

    self::assertTrue($result->isForbidden());
  }

}
