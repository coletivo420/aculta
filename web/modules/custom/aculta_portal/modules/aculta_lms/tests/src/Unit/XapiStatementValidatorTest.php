<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Xapi\XapiStatementValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers xAPI 2.0 statement validation at ingestion (F1).
 */
#[CoversClass(XapiStatementValidator::class)]
final class XapiStatementValidatorTest extends UnitTestCase {

  private const UUID = '9e13c2a4-5b1f-4c8e-9a7d-3f2b1e0d4c5a';

  /**
   * Declaração válida com mbox, Activity e resultado é aceita sem alterações.
   */
  public function testAcceptsValidStatement(): void {
    $statement = $this->statement();
    $this->assertSame($statement, (new XapiStatementValidator())->validate($statement));
  }

  /**
   * actor, verb e object são obrigatórios.
   */
  public function testRefusesMissingRequiredProperty(): void {
    $statement = $this->statement();
    unset($statement['verb']);
    $this->refused($statement);
  }

  /**
   * Propriedades fora da especificação são recusadas, não descartadas.
   */
  public function testRefusesUnknownTopLevelProperty(): void {
    $statement = $this->statement();
    $statement['stored'] = '2026-11-02T10:00:00Z';
    $this->refused($statement);
  }

  /**
   * id precisa ser UUID.
   */
  public function testRefusesNonUuidId(): void {
    $this->refused($this->statement(['id' => 'abc']));
  }

  /**
   * timestamp precisa ser ISO 8601.
   */
  public function testRefusesBadTimestamp(): void {
    $this->refused($this->statement(['timestamp' => '02/11/2026']));
  }

  /**
   * Agent com dois identificadores inversos funcionais é recusado.
   */
  public function testRefusesActorWithTwoIdentifiers(): void {
    $this->refused($this->statement(['actor' => ['mbox' => 'mailto:a@b.org', 'openid' => 'https://x.org/1']]));
  }

  /**
   * mbox precisa ser mailto IRI.
   */
  public function testRefusesMboxWithoutMailto(): void {
    $this->refused($this->statement(['actor' => ['mbox' => 'ana@aculta.org']]));
  }

  /**
   * account exige homePage e name.
   */
  public function testRefusesAccountWithoutName(): void {
    $this->refused($this->statement(['actor' => ['account' => ['homePage' => 'https://aculta.org']]]));
  }

  /**
   * Grupos não são aceitos nesta fase.
   */
  public function testRefusesGroupActor(): void {
    $this->refused($this->statement(['actor' => ['objectType' => 'Group', 'mbox' => 'mailto:g@aculta.org']]));
  }

  /**
   * verb.id precisa ser IRI com esquema.
   */
  public function testRefusesVerbWithoutScheme(): void {
    $this->refused($this->statement(['verb' => ['id' => 'completed']]));
  }

  /**
   * SubStatement não é aceito nesta fase.
   */
  public function testRefusesSubStatementObject(): void {
    $this->refused($this->statement(['object' => ['objectType' => 'SubStatement']]));
  }

  /**
   * Activity exige id IRI.
   */
  public function testRefusesActivityWithoutId(): void {
    // array_replace_recursive mantém o id da base; o objeto é trocado por inteiro.
    $statement = $this->statement();
    $statement['object'] = ['objectType' => 'Activity'];
    $this->refused($statement);
  }

  /**
   * StatementRef com UUID válido é aceito.
   */
  public function testAcceptsStatementRef(): void {
    $this->assertSame(
      ['objectType' => 'StatementRef', 'id' => self::UUID],
      $this->validatedObject(['objectType' => 'StatementRef', 'id' => self::UUID]),
    );
  }

  /**
   * score.scaled fora de [-1, 1] é recusado.
   */
  public function testRefusesScaledOutOfRange(): void {
    $this->refused($this->statement(['result' => ['score' => ['scaled' => 1.5]]]));
  }

  /**
   * score.raw fora de [min, max] é recusado.
   */
  public function testRefusesRawOutsideBounds(): void {
    $this->refused($this->statement(['result' => ['score' => ['raw' => 12, 'min' => 0, 'max' => 10]]]));
  }

  /**
   * min precisa ser menor que max.
   */
  public function testRefusesMinNotBelowMax(): void {
    $this->refused($this->statement(['result' => ['score' => ['min' => 5, 'max' => 5]]]));
  }

  /**
   * duration precisa ser ISO 8601 de duração; PT1H30M é válido.
   */
  public function testDurationFormat(): void {
    $this->refused($this->statement(['result' => ['duration' => '1 hora']]));
    $ok = $this->statement(['result' => ['duration' => 'PT1H30M']]);
    $this->assertSame($ok, (new XapiStatementValidator())->validate($ok));
  }

  /**
   * success e completion são booleanos.
   */
  public function testRefusesNonBooleanSuccess(): void {
    $this->refused($this->statement(['result' => ['success' => 'sim']]));
  }

  /**
   * context aceita só as propriedades previstas.
   */
  public function testRefusesUnknownContextProperty(): void {
    $this->refused($this->statement(['context' => ['platform' => 'web', 'ip' => '10.0.0.1']]));
  }

  /**
   * Declaração acima do teto de tamanho é recusada.
   */
  public function testRefusesOversizedStatement(): void {
    $this->refused($this->statement(['result' => ['response' => str_repeat('a', XapiStatementValidator::MAX_STATEMENT_BYTES)]]));
  }

  /**
   * Declaração base, com sobrescritas.
   *
   * @param array<string, mixed> $overrides
   *
   * @return array<string, mixed>
   */
  private function statement(array $overrides = []): array {
    $base = [
      'id' => self::UUID,
      'actor' => ['mbox' => 'mailto:ana@aculta.org'],
      'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
      'object' => ['objectType' => 'Activity', 'id' => 'https://aculta.org/lms/atividade/1'],
      'result' => ['success' => TRUE, 'score' => ['scaled' => 0.9]],
      'timestamp' => '2026-11-02T10:00:00Z',
    ];
    return array_replace_recursive($base, $overrides);
  }

  /**
   * Valida só o objeto da declaração e devolve o objeto normalizado.
   *
   * @param array<string, mixed> $object
   *
   * @return array<string, mixed>
   */
  private function validatedObject(array $object): array {
    $statement = $this->statement(['object' => $object]);
    return (new XapiStatementValidator())->validate($statement)['object'];
  }

  /**
   * Afirma que a declaração foi recusada com exceção de validação.
   *
   * @param array<string, mixed> $statement
   */
  private function refused(array $statement): void {
    $accepted = TRUE;
    try {
      (new XapiStatementValidator())->validate($statement);
    }
    catch (\UnexpectedValueException) {
      $accepted = FALSE;
    }
    $this->assertSame(FALSE, $accepted);
  }

}
