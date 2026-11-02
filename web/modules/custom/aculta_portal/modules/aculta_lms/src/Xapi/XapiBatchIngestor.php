<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

/**
 * Decide, sem gravar, quais declarações de um lote são aceitas (ADR-041, F3).
 *
 * Função pura. Recebe o corpo JSON do lote, as reivindicações do token já
 * verificadas e os statement_id já gravados. Falha fechado:
 * - lote que não é um array JSON de declarações, ou que passa dos limites, é recusado por inteiro;
 * - cada declaração passa pelo XapiStatementValidator;
 * - o registration de cada declaração deve ser o do token; ausente, recebe o do token;
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
   * @param string $body
   *   Corpo JSON recebido.
   * @param array{uid: int, content: string, registration: string} $claims
   *   Reivindicações do token já verificado.
   * @param string[] $knownStatementIds
   *   statement_id já gravados para a mesma tentativa ou estudante.
   *
   * @return array{accepted: list<array<string, mixed>>, refused: list<array{index: int, reason: string}>}
   *
   * @throws \UnexpectedValueException
   *   Quando o lote inteiro é recusado.
   */
  public function ingest(string $body, array $claims, array $knownStatementIds): array {
    if (strlen($body) > self::MAX_BODY_BYTES) {
      throw new \UnexpectedValueException('Lote acima do tamanho máximo.');
    }
    $batch = json_decode($body, TRUE);
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
      if (!is_array($statement)) {
        $refused[] = ['index' => $index, 'reason' => 'Declaração não é um objeto.'];
        continue;
      }
      $registration = $statement['context']['registration'] ?? NULL;
      if ($registration !== NULL && $registration !== $claims['registration']) {
        $refused[] = ['index' => $index, 'reason' => 'Lançamento diferente do token.'];
        continue;
      }
      $statement['context'] = array_merge($statement['context'] ?? [], ['registration' => $claims['registration']]);

      try {
        $normalized = $this->validator->validate($statement);
      }
      catch (\UnexpectedValueException $e) {
        $refused[] = ['index' => $index, 'reason' => $e->getMessage()];
        continue;
      }

      $id = (string) ($normalized['id'] ?? '');
      if ($id === '') {
        $refused[] = ['index' => $index, 'reason' => 'Declaração sem id: o cliente deve enviar o UUID.'];
        continue;
      }
      if (isset($seen[$id])) {
        $refused[] = ['index' => $index, 'reason' => 'Declaração duplicada.'];
        continue;
      }
      $seen[$id] = TRUE;
      $accepted[] = $normalized;
    }

    return ['accepted' => $accepted, 'refused' => $refused];
  }

}
