<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\CourseLesson;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the ACULTA lesson contract value object (ADR-042, P1).
 */
#[CoversClass(CourseLesson::class)]
final class CourseLessonTest extends UnitTestCase {

  /**
   * Aula válida mantém os campos.
   */
  public function testKeepsFields(): void {
    $lesson = new CourseLesson(10, 2, 'Introdução', TRUE, 0);
    $this->assertSame(10, $lesson->id);
    $this->assertSame(2, $lesson->courseId);
    $this->assertSame('Introdução', $lesson->title);
    $this->assertTrue($lesson->published);
    $this->assertSame(0, $lesson->weight);
  }

  /**
   * Identificador não positivo é recusado.
   */
  public function testRefusesNonPositiveId(): void {
    $this->expectException(\InvalidArgumentException::class);
    new CourseLesson(0, 2, 'x', FALSE, 0);
  }

  /**
   * Posição negativa é recusada.
   */
  public function testRefusesNegativeWeight(): void {
    $this->expectException(\InvalidArgumentException::class);
    new CourseLesson(1, 2, 'x', FALSE, -1);
  }

}
