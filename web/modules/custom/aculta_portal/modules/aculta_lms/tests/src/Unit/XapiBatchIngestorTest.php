<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Xapi\XapiBatchIngestor;
use Drupal\aculta_lms\Xapi\XapiStatementValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers batch ingestion decisions for xAPI statements (ADR-041, F3).
 */
#[CoversClass(XapiBatchIngestor::class)]
final class XapiBatchIngestorTest extends UnitTestCase {

  private const REGISTRATION = '9e13c2a4-5b1f-4c8e-9a7d-3f2b1e0d4c5a';
  private const OTHER_REGISTRATION = '1f2e3d4c-5b6a-4c7d-8e9f-0a1b2c3d4e5f';

  /**
   * Declarações válidas são aceitas e recebem o registration do token quando ausente.
   */
  public function testAcceptsValidStatementAndFillsRegistration(): void {
    $body = json_encode([$this->statement('a0000000-0000-4000-8000-000000000001')]);
    $result = $this->ingestor()->ingest((string) $body, $this->claims(), []);

    $this->assertSame(1, count($result['accepted']));
    $this->assertSame(self::REGISTRATION, $result['accepted'][0]['context']['registration']);
    $this->assertSame([], $result['refused']);
  }

  /**
   * Lote que não é lista JSON é recusado por inteiro.
   */
  public function testRefusesBatchThatIsNotAList(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->ingestor()->ingest('{"id":"x"}', $this->claims(), []);
  }

  /**
   * Lote vazio é recusado.
   */
  public function testRefusesEmptyBatch(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->ingestor()->ingest('[]', $this->claims(), []);
  }

  /**
   * Lote acima do número máximo de declarações é recusado por inteiro.
   */
  public function testRefusesBatchOverStatementLimit(): void {
    $batch = [];
    for ($i = 0; $i <= XapiBatchIngestor::MAX_STATEMENTS; $i++) {
      $batch[] = $this->statement(sprintf('a0000000-0000-4000-8000-%012d', $i));
    }
    $this->expectException(\UnexpectedValueException::class);
    $this->ingestor()->ingest((string) json_encode($batch), $this->claims(), []);
  }

  /**
   * Corpo acima do tamanho máximo é recusado antes de decodificar.
   */
  public function testRefusesBodyOverByteLimit(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->ingestor()->ingest(str_repeat('x', XapiBatchIngestor::MAX_BODY_BYTES + 1), $this->claims(), []);
  }

  /**
   * Declaração de outro lançamento é recusada, sem recusar o lote.
   */
  public function testRefusesOtherRegistrationPerStatement(): void {
    $body = json_encode([
      $this->statement('a0000000-0000-4000-8000-000000000001', self::OTHER_REGISTRATION),
      $this->statement('a0000000-0000-4000-8000-000000000002'),
    ]);
    $result = $this->ingestor()->ingest((string) $body, $this->claims(), []);

    $this->assertSame(1, count($result['accepted']));
    $this->assertSame(0, $result['refused'][0]['index']);
  }

  /**
   * statement_id repetido no mesmo lote: a segunda ocorrência é recusada.
   */
  public function testRefusesDuplicateInsideBatch(): void {
    $id = 'a0000000-0000-4000-8000-000000000001';
    $body = json_encode([$this->statement($id), $this->statement($id)]);
    $result = $this->ingestor()->ingest((string) $body, $this->claims(), []);

    $this->assertSame(1, count($result['accepted']));
    $this->assertSame(1, $result['refused'][0]['index']);
  }

  /**
   * statement_id já gravado é recusado como duplicata.
   */
  public function testRefusesAlreadyStoredStatement(): void {
    $id = 'a0000000-0000-4000-8000-000000000001';
    $body = json_encode([$this->statement($id)]);
    $result = $this->ingestor()->ingest((string) $body, $this->claims(), [$id]);

    $this->assertSame([], $result['accepted']);
    $this->assertSame('Declaração duplicada.', $result['refused'][0]['reason']);
  }

  /**
   * Declaração sem id é recusada: o cliente deve enviar o UUID.
   */
  public function testRefusesStatementWithoutId(): void {
    $statement = $this->statement('a0000000-0000-4000-8000-000000000001');
    unset($statement['id']);
    $result = $this->ingestor()->ingest((string) json_encode([$statement]), $this->claims(), []);

    $this->assertSame([], $result['accepted']);
    $this->assertSame(0, $result['refused'][0]['index']);
  }

  /**
   * Declaração inválida pelo validador é recusada com o motivo dele.
   */
  public function testRefusesInvalidStatementWithValidatorReason(): void {
    $statement = $this->statement('a0000000-0000-4000-8000-000000000001');
    unset($statement['verb']);
    $result = $this->ingestor()->ingest((string) json_encode([$statement]), $this->claims(), []);

    $this->assertSame([], $result['accepted']);
    $this->assertSame('A declaração exige verb.', $result['refused'][0]['reason']);
  }

  private function ingestor(): XapiBatchIngestor {
    return new XapiBatchIngestor(new XapiStatementValidator());
  }

  /**
   * @return array{uid: int, content: string, registration: string}
   */
  private function claims(): array {
    return ['uid' => 42, 'content' => 'https://exemplo.invalid/atividade/1', 'registration' => self::REGISTRATION];
  }

  /**
   * @return array<string, mixed>
   */
  private function statement(string $id, ?string $registration = NULL): array {
    $statement = [
      'id' => $id,
      'actor' => ['mbox' => 'mailto:aluno@example.org'],
      'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
      'object' => ['id' => 'https://exemplo.invalid/atividade/1'],
      'timestamp' => '2026-11-02T10:00:00Z',
    ];
    if ($registration !== NULL) {
      $statement['context'] = ['registration' => $registration];
    }
    return $statement;
  }

}
