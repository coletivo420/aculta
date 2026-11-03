<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Assessment\ObjectiveAnswerScorer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers objective answer scoring for own activity types (ADR-042, P3).
 */
#[CoversClass(ObjectiveAnswerScorer::class)]
final class ObjectiveAnswerScorerTest extends UnitTestCase {

  /**
   * Verdadeiro/falso vale 100 quando acerta e 0 quando erra.
   */
  public function testTrueFalse(): void {
    $scorer = new ObjectiveAnswerScorer();
    $this->assertSame(100.0, $scorer->trueFalse(TRUE, TRUE));
    $this->assertSame(0.0, $scorer->trueFalse(FALSE, TRUE));
  }

  /**
   * Seleção só vale com o conjunto exato, sem crédito parcial e sem depender da ordem.
   */
  public function testSelectRequiresExactSet(): void {
    $scorer = new ObjectiveAnswerScorer();
    $this->assertSame(100.0, $scorer->select(['b', 'a'], ['a', 'b']));
    $this->assertSame(0.0, $scorer->select(['a'], ['a', 'b']));
    $this->assertSame(0.0, $scorer->select(['a', 'b', 'c'], ['a', 'b']));
  }

  /**
   * Seleção sem opção correta cadastrada não pontua.
   */
  public function testSelectWithoutCorrectOptionScoresZero(): void {
    $this->assertSame(0.0, (new ObjectiveAnswerScorer())->select([], []));
  }

  /**
   * Lacunas: percentual de acertos, ignorando caixa, espaços nas pontas e espaços internos repetidos.
   */
  public function testFillInBlanksPercentage(): void {
    $scorer = new ObjectiveAnswerScorer();
    $accepted = [0 => ['Brasil'], 1 => ['São Paulo', 'SP']];
    $this->assertSame(100.0, $scorer->fillInBlanks([0 => '  brasil ', 1 => 'são   paulo'], $accepted));
    $this->assertSame(50.0, $scorer->fillInBlanks([0 => 'Brasil', 1 => 'Rio'], $accepted));
    $this->assertSame(50.0, $scorer->fillInBlanks([0 => 'Brasil'], $accepted));
  }

  /**
   * Lacuna vazia conta como errada, mesmo que a resposta aceita seja vazia.
   */
  public function testEmptyBlankIsWrong(): void {
    $scorer = new ObjectiveAnswerScorer();
    $this->assertSame(0.0, $scorer->fillInBlanks([0 => '   '], [0 => ['Brasil']]));
  }

}
