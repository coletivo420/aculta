<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Assessment\AttemptRule;
use Drupal\aculta_lms\Xapi\XapiProgressDeriver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers xAPI progress derivation from validated statements (F2).
 */
#[CoversClass(XapiProgressDeriver::class)]
final class XapiProgressDeriverTest extends UnitTestCase {

  private const VERB_COMPLETED = 'http://adlnet.gov/expapi/verbs/completed';
  private const VERB_PASSED = 'http://adlnet.gov/expapi/verbs/passed';
  private const VERB_FAILED = 'http://adlnet.gov/expapi/verbs/failed';
  private const VERB_ANSWERED = 'http://adlnet.gov/expapi/verbs/answered';

  /**
   * Conclusão com nota escalonada vira nota de 0 a 100 e passa na regra.
   */
  public function testScaledScoreIsConvertedAndPasses(): void {
    $statements = [$this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.8]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 70.0);

    $this->assertSame(80.0, $result['score']);
    $this->assertSame(AttemptRule::PASSED, $result['status']);
  }

  /**
   * Sem scaled, raw com min e max vira percentual dentro do intervalo.
   */
  public function testRawScoreIsNormalisedWithMinAndMax(): void {
    $statements = [$this->statement(self::VERB_COMPLETED, ['score' => ['raw' => 15, 'min' => 0, 'max' => 20]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 50.0);

    $this->assertSame(75.0, $result['score']);
  }

  /**
   * min ausente vale 0: raw 15 de max 20 dá 75, como no Opigno.
   */
  public function testMissingMinDefaultsToZero(): void {
    $statements = [$this->statement(self::VERB_COMPLETED, ['score' => ['raw' => 15, 'max' => 20]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 50.0);

    $this->assertSame(75.0, $result['score']);
  }

  /**
   * Quando há scaled e raw, o scaled vale.
   */
  public function testScaledWinsOverRaw(): void {
    $statements = [$this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.5, 'raw' => 90, 'min' => 0, 'max' => 100]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 0.0);

    $this->assertSame(50.0, $result['score']);
  }

  /**
   * scaled negativo é tratado como zero; raw fora do intervalo é limitado.
   */
  public function testOutOfRangeScoresAreClamped(): void {
    $deriver = new XapiProgressDeriver();
    $negative = $deriver->result([$this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => -0.4]], 'reg-1', '2026-11-02T10:00:00Z')], AttemptRule::KEEP_LATEST, 0.0);
    $this->assertSame(0.0, $negative['score']);

    $above = $deriver->result([$this->statement(self::VERB_COMPLETED, ['score' => ['raw' => 150, 'min' => 0, 'max' => 100]], 'reg-1', '2026-11-02T10:00:00Z')], AttemptRule::KEEP_LATEST, 0.0);
    $this->assertSame(100.0, $above['score']);
  }

  /**
   * min igual a max não define percentual: não há nota.
   */
  public function testZeroRangeGivesNoScore(): void {
    $statements = [$this->statement(self::VERB_COMPLETED, ['score' => ['raw' => 5, 'min' => 5, 'max' => 5]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 0.0);

    $this->assertNull($result['score']);
    $this->assertSame(AttemptRule::PENDING, $result['status']);
  }

  /**
   * Sem conclusão, a atividade continua pendente mesmo com nota.
   */
  public function testScoreWithoutCompletionStaysPending(): void {
    $statements = [$this->statement(self::VERB_ANSWERED, ['score' => ['scaled' => 1.0]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 70.0);

    $this->assertNull($result['score']);
    $this->assertSame(AttemptRule::PENDING, $result['status']);
    $this->assertFalse($result['attempts'][0]['completed']);
  }

  /**
   * result.completion verdadeiro conclui, mesmo com verbo que não é de conclusão.
   */
  public function testCompletionFlagCompletesTheAttempt(): void {
    $statements = [$this->statement(self::VERB_ANSWERED, ['completion' => TRUE, 'score' => ['scaled' => 0.9]], 'reg-1', '2026-11-02T10:00:00Z')];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 70.0);

    $this->assertSame(AttemptRule::PASSED, $result['status']);
    $this->assertTrue($result['attempts'][0]['completed']);
  }

  /**
   * O verbo passed marca sucesso; o verbo failed marca falha.
   */
  public function testPassedAndFailedVerbsSetSuccess(): void {
    $deriver = new XapiProgressDeriver();
    $passed = $deriver->attempts([$this->statement(self::VERB_PASSED, [], 'reg-1', '2026-11-02T10:00:00Z')]);
    $this->assertTrue($passed[0]['success']);
    $this->assertTrue($passed[0]['completed']);

    $failed = $deriver->attempts([$this->statement(self::VERB_FAILED, [], 'reg-1', '2026-11-02T10:00:00Z')]);
    $this->assertFalse($failed[0]['success']);
  }

  /**
   * O result.success informado tem prioridade sobre o sentido do verbo.
   */
  public function testExplicitSuccessFlagOverridesVerb(): void {
    $statements = [$this->statement(self::VERB_PASSED, ['success' => FALSE], 'reg-1', '2026-11-02T10:00:00Z')];
    $attempts = (new XapiProgressDeriver())->attempts($statements);

    $this->assertFalse($attempts[0]['success']);
  }

  /**
   * Dentro de uma tentativa, a nota mais recente pelo timestamp decide.
   */
  public function testLatestScoreWithinAttemptWinsByTimestamp(): void {
    $statements = [
      $this->statement(self::VERB_ANSWERED, ['score' => ['scaled' => 0.8]], 'reg-1', '2026-11-02T10:05:00Z'),
      $this->statement(self::VERB_ANSWERED, ['score' => ['scaled' => 0.3]], 'reg-1', '2026-11-02T10:00:00Z'),
    ];
    $attempts = (new XapiProgressDeriver())->attempts($statements);

    $this->assertSame(80.0, $attempts[0]['score']);
  }

  /**
   * Declaração sem nota não apaga a nota já derivada da tentativa.
   */
  public function testStatementWithoutScoreKeepsPreviousScore(): void {
    $statements = [
      $this->statement(self::VERB_ANSWERED, ['score' => ['scaled' => 0.6]], 'reg-1', '2026-11-02T10:00:00Z'),
      $this->statement(self::VERB_COMPLETED, [], 'reg-1', '2026-11-02T10:01:00Z'),
    ];
    $attempts = (new XapiProgressDeriver())->attempts($statements);

    $this->assertSame(60.0, $attempts[0]['score']);
    $this->assertTrue($attempts[0]['completed']);
  }

  /**
   * Declarações com registration diferente são tentativas diferentes.
   */
  public function testRegistrationsAreSeparateAttempts(): void {
    $statements = [
      $this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.6]], 'reg-1', '2026-11-02T10:00:00Z'),
      $this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.9]], 'reg-2', '2026-11-02T11:00:00Z'),
    ];
    $attempts = (new XapiProgressDeriver())->attempts($statements);

    $this->assertCount(2, $attempts);
    $this->assertSame('reg-1', $attempts[0]['registration']);
    $this->assertSame('reg-2', $attempts[1]['registration']);
  }

  /**
   * Com KEEP_BEST, a melhor nota entre tentativas fica.
   */
  public function testKeepBestPicksHighestAttempt(): void {
    $statements = [
      $this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.9]], 'reg-1', '2026-11-02T10:00:00Z'),
      $this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.5]], 'reg-2', '2026-11-02T11:00:00Z'),
    ];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_BEST, 70.0);

    $this->assertSame(90.0, $result['score']);
  }

  /**
   * Com KEEP_LATEST, vale a tentativa terminada mais recentemente, mesmo com nota menor.
   */
  public function testKeepLatestPicksMostRecentAttempt(): void {
    $statements = [
      $this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.9]], 'reg-1', '2026-11-02T10:00:00Z'),
      $this->statement(self::VERB_COMPLETED, ['score' => ['scaled' => 0.5]], 'reg-2', '2026-11-02T11:00:00Z'),
    ];
    $result = (new XapiProgressDeriver())->result($statements, AttemptRule::KEEP_LATEST, 70.0);

    $this->assertSame(50.0, $result['score']);
    $this->assertSame(AttemptRule::FAILED, $result['status']);
  }

  /**
   * Sem nenhuma declaração, a atividade está pendente e sem nota.
   */
  public function testNoStatementsIsPending(): void {
    $result = (new XapiProgressDeriver())->result([], AttemptRule::KEEP_LATEST, 70.0);

    $this->assertSame(AttemptRule::PENDING, $result['status']);
    $this->assertNull($result['score']);
    $this->assertSame([], $result['attempts']);
  }

  /**
   * Política desconhecida é recusada pela AttemptRule.
   */
  public function testUnknownPolicyIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new XapiProgressDeriver())->result([], 'average', 70.0);
  }

  /**
   * Monta uma declaração já validada, com o mínimo de campos usados pelo deriver.
   *
   * @param array<string, mixed> $result
   */
  private function statement(string $verb, array $result, string $registration, string $timestamp): array {
    $statement = [
      'actor' => ['mbox' => 'mailto:aluno@example.org'],
      'verb' => ['id' => $verb],
      'object' => ['id' => 'https://example.org/atividade/1'],
      'context' => ['registration' => $registration],
      'timestamp' => $timestamp,
    ];
    if ($result !== []) {
      $statement['result'] = $result;
    }
    return $statement;
  }

}
