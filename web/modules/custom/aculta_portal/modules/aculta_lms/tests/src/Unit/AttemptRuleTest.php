<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Assessment\AttemptRule;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the pure attempt rules.
 */
#[CoversClass(AttemptRule::class)]
final class AttemptRuleTest extends UnitTestCase {

  /**
   * Uma atividade sem avaliação deixa a tentativa pendente, mesmo com nota alta.
   */
  public function testPendingActivityKeepsAttemptPending(): void {
    $this->assertSame(AttemptRule::PENDING, AttemptRule::status(1, 100.0, 50.0));
  }

  /**
   * Sem nota, a tentativa está pendente.
   */
  public function testMissingScoreKeepsAttemptPending(): void {
    $this->assertSame(AttemptRule::PENDING, AttemptRule::status(0, NULL, 50.0));
  }

  /**
   * Nota exatamente no mínimo é aprovação.
   */
  public function testScoreAtMinimumPasses(): void {
    $this->assertSame(AttemptRule::PASSED, AttemptRule::status(0, 70.0, 70.0));
  }

  /**
   * Nota abaixo do mínimo reprova, com todas as atividades avaliadas.
   */
  public function testScoreBelowMinimumFails(): void {
    $this->assertSame(AttemptRule::FAILED, AttemptRule::status(0, 69.9, 70.0));
  }

  /**
   * Contagem pendente negativa é entrada inválida.
   */
  public function testNegativePendingCountIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    AttemptRule::status(-1, 80.0, 50.0);
  }

  /**
   * Nota mínima fora da escala é entrada inválida.
   */
  public function testRequiredScoreOutOfRangeIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    AttemptRule::status(0, 80.0, 101.0);
  }

  /**
   * Política "mais recente" ignora a nota e escolhe o término mais novo.
   */
  public function testKeepLatestIgnoresScore(): void {
    $kept = AttemptRule::keep([
      ['score' => 95.0, 'finished' => 100],
      ['score' => 40.0, 'finished' => 200],
    ], AttemptRule::KEEP_LATEST);
    $this->assertSame(40.0, $kept['score']);
  }

  /**
   * Política "melhor" escolhe a maior nota, independentemente da data.
   */
  public function testKeepBestPicksHighestScore(): void {
    $kept = AttemptRule::keep([
      ['score' => 95.0, 'finished' => 100],
      ['score' => 40.0, 'finished' => 200],
    ], AttemptRule::KEEP_BEST);
    $this->assertSame(95.0, $kept['score']);
  }

  /**
   * Empate na política "melhor" fica com a tentativa mais recente.
   */
  public function testKeepBestTieKeepsLatest(): void {
    $kept = AttemptRule::keep([
      ['score' => 80.0, 'finished' => 100],
      ['score' => 80.0, 'finished' => 300],
      ['score' => 80.0, 'finished' => 200],
    ], AttemptRule::KEEP_BEST);
    $this->assertSame(300, $kept['finished']);
  }

  /**
   * Sem tentativas, não há resultado mantido.
   */
  public function testKeepWithoutAttemptsIsNull(): void {
    $this->assertNull(AttemptRule::keep([], AttemptRule::KEEP_LATEST));
  }

  /**
   * Política desconhecida é entrada inválida.
   */
  public function testUnknownPolicyIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    AttemptRule::keep([['score' => 1.0, 'finished' => 1]], 'random');
  }

}
