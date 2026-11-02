<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Scorm\ScormStatusMapper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers normalization of SCORM 1.2 and 2004 runtime status values.
 */
#[CoversClass(ScormStatusMapper::class)]
final class ScormStatusMapperTest extends UnitTestCase {

  /**
   * Cada valor de lesson_status do SCORM 1.2 vira o estado esperado.
   */
  public function testLessonStatusVersion12(): void {
    $mapper = new ScormStatusMapper();

    $passed = $mapper->normalize('1.2', 'passed');
    $this->assertSame(['completion' => 'completed', 'success' => 'passed', 'state' => 'passed'], $passed);

    $failed = $mapper->normalize('1.2', 'failed');
    $this->assertSame(['completion' => 'completed', 'success' => 'failed', 'state' => 'failed'], $failed);

    $completed = $mapper->normalize('1.2', 'completed');
    $this->assertSame(['completion' => 'completed', 'success' => 'unknown', 'state' => 'completed'], $completed);

    $incomplete = $mapper->normalize('1.2', 'incomplete');
    $this->assertSame(['completion' => 'incomplete', 'success' => 'unknown', 'state' => 'in_progress'], $incomplete);

    $browsed = $mapper->normalize('1.2', 'browsed');
    $this->assertSame(['completion' => 'incomplete', 'success' => 'unknown', 'state' => 'in_progress'], $browsed);

    $notAttempted = $mapper->normalize('1.2', 'not attempted');
    $this->assertSame(['completion' => 'not_attempted', 'success' => 'unknown', 'state' => 'not_started'], $notAttempted);
  }

  /**
   * Valores de SCORM 1.2 são tratados sem diferença de maiúsculas e espaços.
   */
  public function testLessonStatusIsCaseInsensitive(): void {
    $result = (new ScormStatusMapper())->normalize('1.2', '  PASSED ');
    $this->assertSame('passed', $result['state']);
  }

  /**
   * SCORM 2004 combina completion_status e success_status independentes.
   */
  public function testCompletionAndSuccessVersion2004(): void {
    $mapper = new ScormStatusMapper();

    $this->assertSame(
      ['completion' => 'completed', 'success' => 'passed', 'state' => 'passed'],
      $mapper->normalize('2004', '', 'completed', 'passed'),
    );
    $this->assertSame(
      ['completion' => 'completed', 'success' => 'failed', 'state' => 'failed'],
      $mapper->normalize('2004', '', 'completed', 'failed'),
    );
    $this->assertSame(
      ['completion' => 'completed', 'success' => 'unknown', 'state' => 'completed'],
      $mapper->normalize('2004', '', 'completed', 'unknown'),
    );
    $this->assertSame(
      ['completion' => 'incomplete', 'success' => 'unknown', 'state' => 'in_progress'],
      $mapper->normalize('2004', '', 'incomplete', 'unknown'),
    );
    $this->assertSame(
      ['completion' => 'not_attempted', 'success' => 'unknown', 'state' => 'not_started'],
      $mapper->normalize('2004', '', 'not attempted', 'unknown'),
    );
  }

  /**
   * Sucesso informado sem conclusão ainda conta como aprovado ou reprovado.
   */
  public function testSuccessWithoutCompletionIsStillFinal(): void {
    $result = (new ScormStatusMapper())->normalize('2004', '', 'incomplete', 'passed');
    $this->assertSame('passed', $result['state']);
  }

  /**
   * Valor fora da especificação é recusado, não convertido em padrão.
   */
  public function testUnknownLessonStatusIsRefused(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormStatusMapper())->normalize('1.2', 'done');
  }

  /**
   * Valor de conclusão inválido em SCORM 2004 é recusado.
   */
  public function testUnknownCompletionStatusIsRefused(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormStatusMapper())->normalize('2004', '', 'finished', 'unknown');
  }

  /**
   * Valor de sucesso inválido em SCORM 2004 é recusado.
   */
  public function testUnknownSuccessStatusIsRefused(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormStatusMapper())->normalize('2004', '', 'completed', 'great');
  }

  /**
   * Versão fora de 1.2 e 2004 é recusada.
   */
  public function testUnknownVersionIsRefused(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new ScormStatusMapper())->normalize('2013', 'passed');
  }

}
