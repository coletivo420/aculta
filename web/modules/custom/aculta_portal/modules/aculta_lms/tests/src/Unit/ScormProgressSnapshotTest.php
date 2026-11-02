<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Scorm\ScormProgressSnapshot;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers allowlisting, size limits and state derivation of SCORM cmi data.
 */
#[CoversClass(ScormProgressSnapshot::class)]
final class ScormProgressSnapshotTest extends UnitTestCase {

  /**
   * Dados válidos de SCORM 1.2 são mantidos e o estado é derivado.
   */
  public function testKeepsAllowedVersion12Data(): void {
    $result = (new ScormProgressSnapshot())->fromCmi('1.2', [
      'core' => ['lesson_status' => 'passed', 'lesson_location' => 'slide-3', 'score' => ['raw' => '90']],
      'suspend_data' => 'dados',
      'interactions' => [['id' => 'q1', 'type' => 'choice', 'result' => 'correct']],
    ]);

    $this->assertSame('passed', $result['state']);
    $this->assertSame('slide-3', $result['cmi']['core']['lesson_location']);
    $this->assertSame('90', $result['cmi']['core']['score']['raw']);
    $this->assertSame('dados', $result['cmi']['suspend_data']);
    $this->assertSame('q1', $result['cmi']['interactions'][0]['id']);
  }

  /**
   * Elementos fora do schema, como identificação do aluno, são descartados.
   */
  public function testDropsElementsOutsideSchema(): void {
    $result = (new ScormProgressSnapshot())->fromCmi('1.2', [
      'core' => ['lesson_status' => 'incomplete', 'student_id' => '12345', 'student_name' => 'Ana'],
      'launch_data' => 'x',
      'interactions' => [['id' => 'q1', 'student_response' => 'a', 'extra' => 'não guardar']]
    ]);

    $this->assertSame(FALSE, array_key_exists('student_id', $result['cmi']['core']));
    $this->assertSame(FALSE, array_key_exists('student_name', $result['cmi']['core']));
    $this->assertSame(FALSE, array_key_exists('launch_data', $result['cmi']));
    $this->assertSame(FALSE, array_key_exists('extra', $result['cmi']['interactions'][0]));
  }

  /**
   * Suspend data acima de 4096 caracteres em SCORM 1.2 é recusado.
   */
  public function testRefusesOversizedSuspendData12(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('1.2', ['suspend_data' => str_repeat('a', 4097)]);
  }

  /**
   * Em SCORM 2004 o mesmo campo aceita até 64000 caracteres.
   */
  public function testAcceptsSuspendDataUpTo2004Limit(): void {
    $result = (new ScormProgressSnapshot())->fromCmi('2004', ['suspend_data' => str_repeat('a', 64000)]);
    $this->assertSame(64000, strlen($result['cmi']['suspend_data']));
  }

  /**
   * Suspend data acima de 64000 caracteres em SCORM 2004 é recusado.
   */
  public function testRefusesOversizedSuspendData2004(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('2004', ['suspend_data' => str_repeat('a', 64001)]);
  }

  /**
   * Interações acima do limite são recusadas.
   */
  public function testRefusesTooManyInteractions(): void {
    $this->expectException(\UnexpectedValueException::class);
    $items = array_fill(0, ScormProgressSnapshot::MAX_INTERACTIONS + 1, ['id' => 'q']);
    (new ScormProgressSnapshot())->fromCmi('1.2', ['interactions' => $items]);
  }

  /**
   * Status fora da especificação é recusado pelo mapeador.
   */
  public function testRefusesUnknownLessonStatus(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('1.2', ['core' => ['lesson_status' => 'done']]);
  }

  /**
   * Pontuação não numérica é recusada.
   */
  public function testRefusesNonNumericScore(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('2004', ['score' => ['raw' => 'alto']]);
  }

  /**
   * Valor composto onde deveria haver texto é recusado.
   */
  public function testRefusesNestedValueWhereTextIsExpected(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('1.2', ['suspend_data' => ['a' => 'b']]);
  }

  /**
   * Ramo que deveria ser objeto e veio como texto é recusado.
   */
  public function testRefusesScalarWhereObjectIsExpected(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('1.2', ['core' => 'x']);
  }

  /**
   * Em SCORM 2004 o estado vem de completion_status e success_status.
   */
  public function testVersion2004State(): void {
    $result = (new ScormProgressSnapshot())->fromCmi('2004', [
      'completion_status' => 'completed',
      'success_status' => 'failed',
    ]);
    $this->assertSame('failed', $result['state']);
    $this->assertSame('completed', $result['completion']);
  }

  /**
   * Sem status informado, o estado é não iniciado.
   */
  public function testMissingStatusIsNotStarted(): void {
    $result = (new ScormProgressSnapshot())->fromCmi('1.2', []);
    $this->assertSame('not_started', $result['state']);
  }

  /**
   * Versão desconhecida é recusada.
   */
  public function testRefusesUnknownVersion(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormProgressSnapshot())->fromCmi('2013', []);
  }

}
