<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the ACULTA activity and answer contract value objects (ADR-042, P1).
 */
#[CoversClass(CourseActivity::class)]
#[CoversClass(CourseAnswer::class)]
final class CourseActivityAnswerTest extends UnitTestCase {

  /**
   * Atividade válida mantém os campos.
   */
  public function testActivityKeepsFields(): void {
    $activity = new CourseActivity(5, 10, 'objective', TRUE);
    $this->assertSame('objective', $activity->type);
    $this->assertTrue($activity->graded);
  }

  /**
   * Tipo com maiúsculas ou espaços é recusado.
   */
  public function testActivityRefusesInvalidType(): void {
    $this->expectException(\InvalidArgumentException::class);
    new CourseActivity(5, 10, 'Objective Type', FALSE);
  }

  /**
   * Resposta submetida pode não ter nota.
   */
  public function testSubmittedAnswerMayHaveNoScore(): void {
    $answer = new CourseAnswer(1, 5, 42, CourseAnswer::STATE_SUBMITTED, NULL);
    $this->assertNull($answer->score);
  }

  /**
   * Resposta avaliada sem nota é recusada.
   */
  public function testGradedAnswerRequiresScore(): void {
    $this->expectException(\InvalidArgumentException::class);
    new CourseAnswer(1, 5, 42, CourseAnswer::STATE_GRADED, NULL);
  }

  /**
   * Nota acima de 100 é recusada.
   */
  public function testScoreAboveRangeIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    new CourseAnswer(1, 5, 42, CourseAnswer::STATE_GRADED, 100.5);
  }

}
