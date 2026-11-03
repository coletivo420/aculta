<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\aculta_lms\Entity\ProgressEntity;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Tests domain constraints shared by the internal learning entities. */
#[CoversClass(AnswerEntity::class)]
#[CoversClass(ActivityEntity::class)]
#[CoversClass(LessonEntity::class)]
#[CoversClass(ProgressEntity::class)]
final class LearningEntitiesTest extends UnitTestCase {

  public function testGradedAnswerRequiresScore(): void {
    $this->expectException(\InvalidArgumentException::class);
    AnswerEntity::assertAnswerRules(CourseAnswer::STATE_GRADED, NULL);
  }

  public function testAnswerStateMustBeKnown(): void {
    $this->expectException(\InvalidArgumentException::class);
    AnswerEntity::assertAnswerRules('unknown', 50.0);
  }

  public function testProgressStatusMustBeKnown(): void {
    $this->expectException(\InvalidArgumentException::class);
    ProgressEntity::assertValidStatus('unknown');
  }

  public function testProgressStatusFromContractIsAccepted(): void {
    ProgressEntity::assertValidStatus(CourseProgress::STATUS_PROGRESS);
    $this->assertTrue(TRUE);
  }

  public function testActivityUsesContractTypeAndNonNegativeWeightRules(): void {
    ActivityEntity::assertActivityRules('objective', 10, TRUE, 0);
    $this->expectException(\InvalidArgumentException::class);
    ActivityEntity::assertActivityRules('Invalid type', 10, TRUE, 0);
  }

  public function testActivityRejectsNegativeWeight(): void {
    $this->expectException(\InvalidArgumentException::class);
    ActivityEntity::assertActivityRules('objective', 10, TRUE, -1);
  }

  public function testLessonUsesContractNonNegativeWeightRule(): void {
    $this->expectException(\InvalidArgumentException::class);
    LessonEntity::assertLessonRules(2, 'Aula', FALSE, -1);
  }

}
