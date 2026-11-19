<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

/**
 * Decide, sem gravar, quais declarações de um lote são aceitas (ADR-041, F3).
 *
 * Função pura. Recebe o corpo JSON do lote, as reivindicações do token já
 * verificadas e os statement_id já gravados. Falha fechado:
 * - Lote que não é um array JSON de declarações, ou que passa dos limites,
 *   é recusado por inteiro;
 * - cada declaração passa pelo XapiStatementValidator;
 * - O registration de cada declaração deve ser o do token; ausente, recebe
 *   o do token;
 * - statement_id repetido no lote ou já gravado é recusado como duplicata.
 *
 * O resultado traz as declarações aceitas, já normalizadas, e as recusadas
 * com o índice e o motivo. Quem chama grava e aplica a retenção.
 */
final class XapiBatchIngestor {

  /**
   * Máximo de declarações por lote.
   */
  public const MAX_STATEMENTS = 50;

  /**
   * Máximo do corpo do lote, em bytes.
   */
  public const MAX_BODY_BYTES = 1048576;

  public function __construct(private readonly XapiStatementValidator $validator) {}

  /**
   * Valida e ingere um lote de statements em JSON.
   *
   * @param string $body
   *   Corpo JSON recebido.
   * @param array<array-key, mixed> $claims
   *   Reivindicações do token já verificado.
   * @param list<string> $knownStatementIds
   *   Statement IDs já gravados para a mesma tentativa ou estudante.
   *
   * @return array{accepted: list<array<string, mixed>>, refused: list<array{index: int, reason: string}>}
   *   Normalized accepted statements and per-statement refusals.
   *
   * @throws \UnexpectedValueException
   *   Quando o lote inteiro é recusado.
   */
  public function ingest(string $body, array $claims, array $knownStatementIds): array {
    if (strlen($body) > self::MAX_BODY_BYTES) {
      throw new \UnexpectedValueException('Lote acima do tamanho máximo.');
    }
    $batch = json_decode($body, TRUE);
    return $this->ingestDecoded($batch, $claims, $knownStatementIds);
  }

  /**
   * Validates and ingests a batch that has already been decoded from JSON.
   *
   * @param mixed $batch
   *   Decoded JSON payload.
   * @param array<array-key, mixed> $claims
   *   Claims from the verified attempt token.
   * @param list<string> $knownStatementIds
   *   Statement IDs already stored for the attempt or student.
   *
   * @return array{accepted: list<array<string, mixed>>, refused: list<array{index: int, reason: string}>}
   *   Normalized accepted statements and per-statement refusals.
   */
  public function ingestDecoded(mixed $batch, array $claims, array $knownStatementIds): array {
    $claims = $this->validatedClaims($claims);
    if (!is_array($batch) || !array_is_list($batch)) {
      throw new \UnexpectedValueException('O lote deve ser uma lista JSON de declarações.');
    }
    if ($batch === []) {
      throw new \UnexpectedValueException('Lote vazio.');
    }
    if (count($batch) > self::MAX_STATEMENTS) {
      throw new \UnexpectedValueException('Lote com declarações demais.');
    }

    $accepted = [];
    $refused = [];
    $seen = array_fill_keys($knownStatementIds, TRUE);

    foreach ($batch as $index => $statement) {
      if (!is_array($statement) || !$this->hasStringKeys($statement)) {
        $refused[] = ['index' => $index, 'reason' => 'Declaração não é um objeto.'];
        continue;
      }
      $context = array_key_exists('context', $statement) ? $statement['context'] : [];
      if (!is_array($context) || !$this->hasStringKeys($context)) {
        $refused[] = ['index' => $index, 'reason' => 'context deve ser um objeto.'];
        continue;
      }
      $registration = $context['registration'] ?? NULL;
      if ($registration !== NULL && (!is_string($registration) || $registration !== $claims['registration'])) {
        $refused[] = ['index' => $index, 'reason' => 'Lançamento diferente do token.'];
        continue;
      }
      $statement['context'] = array_merge($context, ['registration' => $claims['registration']]);

      try {
        $normalized = $this->validator->validate($statement);
      }
      catch (\UnexpectedValueException $e) {
        $refused[] = ['index' => $index, 'reason' => $e->getMessage()];
        continue;
      }

      $id = $normalized['id'] ?? NULL;
      if (!is_string($id) || $id === '') {
        $refused[] = ['index' => $index, 'reason' => 'Declaração sem id: o cliente deve enviar o UUID.'];
        continue;
      }
      if (isset($seen[$id])) {
        $refused[] = ['index' => $index, 'reason' => 'Declaração duplicada.'];
        continue;
      }
      $object = $normalized['object'] ?? NULL;
      $objectId = is_array($object) ? ($object['id'] ?? NULL) : NULL;
      if (!is_string($objectId) || $objectId !== $claims['content']) {
        $refused[] = ['index' => $index, 'reason' => 'Conteúdo diferente do token.'];
        continue;
      }
      $actor = $normalized['actor'] ?? NULL;
      $actorId = is_array($actor) ? ($actor['mbox_sha1sum'] ?? NULL) : NULL;
      if (!is_string($actorId) || $actorId !== strtolower($claims['actor_mbox_sha1sum'])) {
        $refused[] = ['index' => $index, 'reason' => 'Ator diferente do token.'];
        continue;
      }
      $seen[$id] = TRUE;
      $accepted[] = $normalized;
    }

    return ['accepted' => $accepted, 'refused' => $refused];
  }

  /**
   * Determines whether a decoded JSON object uses only string keys.
   *
   * @param array<array-key, mixed> $value
   *   Decoded JSON object.
   *
   * @phpstan-assert-if-true array<string, mixed> $value
   */
  private function hasStringKeys(array $value): bool {
    foreach (array_keys($value) as $key) {
      if (!is_string($key)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Narrows claims from the verified token to the fields used by ingestion.
   *
   * @param array<array-key, mixed> $claims
   *   Claims from the verified attempt token.
   *
   * @return array{uid: int, content: string, registration: string, lesson_status_id: int, actor_mbox_sha1sum: string}
   *   Claims required to enforce statement ownership.
   *
   * @throws \UnexpectedValueException
   *   When a required claim has an unexpected type.
   */
  private function validatedClaims(array $claims): array {
    if (!isset($claims['uid'], $claims['content'], $claims['registration'], $claims['lesson_status_id'], $claims['actor_mbox_sha1sum'])
      || !is_int($claims['uid'])
      || !is_string($claims['content'])
      || !is_string($claims['registration'])
      || !is_int($claims['lesson_status_id'])
      || !is_string($claims['actor_mbox_sha1sum'])) {
      throw new \UnexpectedValueException('Reivindicações inválidas para ingestão.');
    }

    return [
      'uid' => $claims['uid'],
      'content' => $claims['content'],
      'registration' => $claims['registration'],
      'lesson_status_id' => $claims['lesson_status_id'],
      'actor_mbox_sha1sum' => $claims['actor_mbox_sha1sum'],
    ];
  }

}
