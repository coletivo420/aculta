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
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

/**
 * Tests domain constraints shared by the internal learning entities. */
#[CoversClass(AnswerEntity::class)]
#[CoversClass(ActivityEntity::class)]
#[CoversClass(LessonEntity::class)]
#[CoversClass(ProgressEntity::class)]
final class LearningEntitiesTest extends UnitTestCase {

  /**
   * Verifica que uma resposta avaliada exige nota.
   */
  public function testGradedAnswerRequiresScore(): void {
    $this->expectException(\InvalidArgumentException::class);
    AnswerEntity::assertAnswerRules(CourseAnswer::STATE_GRADED, NULL);
  }

  /**
   * Verifica que o estado da resposta precisa ser conhecido.
   */
  public function testAnswerStateMustBeKnown(): void {
    $this->expectException(\InvalidArgumentException::class);
    AnswerEntity::assertAnswerRules('unknown', 50.0);
  }

  /**
   * Verifica que o status de progresso precisa ser conhecido.
   */
  public function testProgressStatusMustBeKnown(): void {
    $this->expectException(\InvalidArgumentException::class);
    ProgressEntity::assertValidStatus('unknown');
  }

  /**
   * Verifica que o status de progresso definido no contrato é aceito.
   */
  #[DoesNotPerformAssertions]
  public function testProgressStatusFromContractIsAccepted(): void {
    ProgressEntity::assertValidStatus(CourseProgress::STATUS_PROGRESS);
  }

  /**
   * Verifica as regras de tipo e peso não negativo da atividade.
   */
  public function testActivityUsesContractTypeAndNonNegativeWeightRules(): void {
    ActivityEntity::assertActivityRules('objective', 10, TRUE, 0);
    $this->expectException(\InvalidArgumentException::class);
    ActivityEntity::assertActivityRules('Invalid type', 10, TRUE, 0);
  }

  /**
   * Verifica que a atividade rejeita peso negativo.
   */
  public function testActivityRejectsNegativeWeight(): void {
    $this->expectException(\InvalidArgumentException::class);
    ActivityEntity::assertActivityRules('objective', 10, TRUE, -1);
  }

  /**
   * Verifica a regra de peso não negativo da lição.
   */
  public function testLessonUsesContractNonNegativeWeightRule(): void {
    $this->expectException(\InvalidArgumentException::class);
    LessonEntity::assertLessonRules(2, 'Aula', FALSE, -1);
  }

}
