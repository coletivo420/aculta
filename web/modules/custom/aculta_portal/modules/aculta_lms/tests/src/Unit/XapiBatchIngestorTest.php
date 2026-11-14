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
  private const ACTOR_SHA1 = '0123456789abcdef0123456789abcdef01234567';

  /**
   * Declarações válidas são aceitas e recebem o registration do token quando ausente.
   */
  public function testAcceptsValidStatementAndFillsRegistration(): void {
    $body = json_encode([$this->statement('a0000000-0000-4000-8000-000000000001')]);
    $result = $this->ingestor()->ingest((string) $body, $this->claims(), []);

    $this->assertCount(1, $result['accepted']);
    $acceptedStatement = $result['accepted'][0];
    $this->assertIsArray($acceptedStatement['context'] ?? NULL);
    $this->assertSame(self::REGISTRATION, $acceptedStatement['context']['registration'] ?? NULL);
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

    $this->assertCount(1, $result['accepted']);
    $this->assertSame(0, $result['refused'][0]['index']);
  }

  /**
   * Malformed statements and contexts are refused at the decoded JSON boundary.
   */
  public function testRefusesMalformedDecodedStatementAndContextShapes(): void {
    $ingestor = $this->ingestor();
    $statementWithIntegerKey = $this->statement('a0000000-0000-4000-8000-000000000001');
    $statementWithIntegerKey[0] = 'unexpected';
    $statements = [
      $statementWithIntegerKey,
      array_replace($this->statement('a0000000-0000-4000-8000-000000000002'), ['context' => 'invalid']),
      array_replace($this->statement('a0000000-0000-4000-8000-000000000003'), ['context' => ['registration' => 42]]),
      array_replace($this->statement('a0000000-0000-4000-8000-000000000004'), ['id' => 42]),
    ];

    $result = $ingestor->ingestDecoded($statements, $this->claims(), []);

    $this->assertSame([], $result['accepted']);
    $this->assertCount(4, $result['refused']);
    $this->assertSame('Declaração não é um objeto.', $result['refused'][0]['reason']);
    $this->assertSame('context deve ser um objeto.', $result['refused'][1]['reason']);
    $this->assertSame('Lançamento diferente do token.', $result['refused'][2]['reason']);
    $this->assertSame('id deve ser um UUID.', $result['refused'][3]['reason']);
  }

  /**
   * Claims with missing or unexpected values cannot enter the ingestion path.
   */
  public function testRejectsMalformedClaims(): void {
    $claims = array_replace($this->claims(), ['uid' => '42']);

    $this->expectException(\UnexpectedValueException::class);
    $this->ingestor()->ingestDecoded(
      [$this->statement('a0000000-0000-4000-8000-000000000001')],
      $claims,
      [],
    );
  }

  /**
   * Statement ID repetido no mesmo lote: a segunda ocorrência é recusada.
   */
  public function testRefusesDuplicateInsideBatch(): void {
    $id = 'a0000000-0000-4000-8000-000000000001';
    $body = json_encode([$this->statement($id), $this->statement($id)]);
    $result = $this->ingestor()->ingest((string) $body, $this->claims(), []);

    $this->assertCount(1, $result['accepted']);
    $this->assertSame(1, $result['refused'][0]['index']);
  }

  /**
   * Statement ID já gravado é recusado como duplicata.
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

  /**
   * Declaração de conteúdo diferente é recusada.
   */
  public function testRefusesStatementForDifferentContent(): void {
    $statement = $this->statement('a0000000-0000-4000-8000-000000000001');
    $this->assertIsArray($statement['object'] ?? NULL);
    $statement['object']['id'] = 'https://teste.invalid/atividade/other';
    $result = $this->ingestor()->ingest((string) json_encode([$statement]), $this->claims(), []);

    $this->assertSame([], $result['accepted']);
    $this->assertSame('Conteúdo diferente do token.', $result['refused'][0]['reason']);
  }

  /**
   * Declaração de ator diferente é recusada.
   */
  public function testRefusesStatementForDifferentActor(): void {
    $statement = $this->statement('a0000000-0000-4000-8000-000000000001');
    $this->assertIsArray($statement['actor'] ?? NULL);
    $statement['actor']['mbox_sha1sum'] = str_repeat('f', 40);
    $result = $this->ingestor()->ingest((string) json_encode([$statement]), $this->claims(), []);

    $this->assertSame([], $result['accepted']);
    $this->assertSame('Ator diferente do token.', $result['refused'][0]['reason']);
  }

  /**
   * Creates the ingestor under test.
   */
  private function ingestor(): XapiBatchIngestor {
    return new XapiBatchIngestor(new XapiStatementValidator());
  }

  /**
   * Monta as claims do token usadas nos testes.
   *
   * @return array{uid: int, content: string, registration: string,
   *   lesson_status_id: int, actor_mbox_sha1sum: string}
   */
  private function claims(): array {
    return [
      'uid' => 42,
      'content' => 'https://exemplo.invalid/atividade/1',
      'registration' => self::REGISTRATION,
      'lesson_status_id' => 2468,
      'actor_mbox_sha1sum' => self::ACTOR_SHA1,
    ];
  }

  /**
   * Monta um statement xAPI para o teste.
   *
   * @return array<string, mixed>
   *   Carga do statement.
   */
  private function statement(string $id, ?string $registration = NULL): array {
    $statement = [
      'id' => $id,
      'actor' => ['mbox_sha1sum' => self::ACTOR_SHA1],
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
