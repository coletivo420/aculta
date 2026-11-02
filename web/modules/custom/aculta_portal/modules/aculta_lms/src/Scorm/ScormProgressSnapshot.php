<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Scorm;

/**
 * Sanitiza o cmi enviado pelo conteúdo SCORM antes de guardar a tentativa.
 *
 * Só aceita os elementos listados em schema, com tipo e limite de tamanho. O
 * que não estiver no schema é descartado, não guardado. Valores de status são
 * validados pelo ScormStatusMapper, e o estado normalizado é derivado aqui.
 *
 * Função pura, sem Drupal. Quem chama decide onde gravar o resultado (decisão:
 * campo de dados da resposta do LMS, com o cmi bruto sanitizado).
 */
final class ScormProgressSnapshot {

  /**
   * Número máximo de interações guardadas por tentativa.
   */
  public const MAX_INTERACTIONS = 200;

  /**
   * Schema SCORM 1.2 (elementos do cmi).
   *
   * @var array<string, mixed>
   */
  private const SCHEMA_12 = [
    'core' => [
      'lesson_location' => ['text', 255],
      'lesson_status' => ['status', 32],
      'entry' => ['text', 32],
      'exit' => ['text', 32],
      'session_time' => ['text', 32],
      'score' => [
        'raw' => ['number', 32],
        'min' => ['number', 32],
        'max' => ['number', 32],
      ],
    ],
    'suspend_data' => ['text', 4096],
    'interactions' => [
      'id' => ['text', 255],
      'type' => ['text', 32],
      'result' => ['text', 255],
      'time' => ['text', 32],
      'student_response' => ['text', 255],
    ],
  ];

  /**
   * Schema SCORM 2004 (elementos do cmi).
   *
   * @var array<string, mixed>
   */
  private const SCHEMA_2004 = [
    'location' => ['text', 1000],
    'exit' => ['text', 32],
    'entry' => ['text', 32],
    'completion_status' => ['status', 32],
    'success_status' => ['status', 32],
    'session_time' => ['text', 32],
    'score' => [
      'raw' => ['number', 32],
      'min' => ['number', 32],
      'max' => ['number', 32],
      'scaled' => ['number', 32],
    ],
    'suspend_data' => ['text', 64000],
    'interactions' => [
      'id' => ['text', 1000],
      'type' => ['text', 32],
      'description' => ['text', 1000],
      'result' => ['text', 255],
      'timestamp' => ['text', 32],
      'learner_response' => ['text', 4000],
    ],
  ];

  /**
   * Sanitiza o cmi de uma versão SCORM.
   *
   * @param string $version
   *   '1.2' ou '2004'.
   * @param array<string, mixed> $cmi
   *   Árvore cmi recebida do conteúdo (já convertida em array).
   *
   * @return array{version: string, state: string, completion: string, success: string, cmi: array<string, mixed>}
   *
   * @throws \UnexpectedValueException
   *   Quando a versão, um status ou um tipo de valor não é aceito.
   */
  public function fromCmi(string $version, array $cmi): array {
    $schema = match ($version) {
      '1.2' => self::SCHEMA_12,
      '2004' => self::SCHEMA_2004,
      default => throw new \UnexpectedValueException('Versão SCORM desconhecida.'),
    };

    $sanitized = $this->filter($schema, $cmi, $version);
    $status = $this->status($version, $sanitized);

    return [
      'version' => $version,
      'state' => $status['state'],
      'completion' => $status['completion'],
      'success' => $status['success'],
      'cmi' => $sanitized,
    ];
  }

  /**
   * Aplica o schema recursivamente, descartando o que não está nele.
   *
   * @param array<string, mixed> $schema
   * @param array<string, mixed> $data
   *
   * @return array<string, mixed>
   */
  private function filter(array $schema, array $data, string $version): array {
    $out = [];
    foreach ($schema as $key => $rule) {
      if (!array_key_exists($key, $data)) {
        continue;
      }
      $value = $data[$key];
      if ($key === 'interactions') {
        $out[$key] = $this->interactions($rule, $value);
        continue;
      }
      if (is_array($rule) && !$this->isLeaf($rule)) {
        if (!is_array($value)) {
          throw new \UnexpectedValueException("Elemento {$key} deveria ser um objeto.");
        }
        $out[$key] = $this->filter($rule, $value, $version);
        continue;
      }
      $out[$key] = $this->leaf($key, $rule, $value);
    }
    return $out;
  }

  /**
   * Interações: lista limitada, cada item filtrado pelo schema de interação.
   *
   * @param array<string, mixed> $rule
   * @param mixed $value
   *
   * @return list<array<string, mixed>>
   */
  private function interactions(array $rule, mixed $value): array {
    if (!is_array($value) || !array_is_list($value)) {
      throw new \UnexpectedValueException('Interações deveriam ser uma lista.');
    }
    if (count($value) > self::MAX_INTERACTIONS) {
      throw new \UnexpectedValueException('Interações acima do limite.');
    }
    $list = [];
    foreach ($value as $item) {
      if (!is_array($item)) {
        throw new \UnexpectedValueException('Cada interação deveria ser um objeto.');
      }
      $clean = [];
      foreach ($rule as $key => $leaf) {
        if (array_key_exists($key, $item)) {
          $clean[$key] = $this->leaf($key, $leaf, $item[$key]);
        }
      }
      $list[] = $clean;
    }
    return $list;
  }

  /**
   * Valida um valor folha pelo tipo e pelo limite.
   *
   * @param array{0: string, 1: int, 2?: mixed} $rule
   */
  private function leaf(string $key, array $rule, mixed $value): string {
    [$type, $max] = $rule;
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
      throw new \UnexpectedValueException("Valor de {$key} tem tipo inválido.");
    }
    $text = (string) $value;
    if (strlen($text) > $max) {
      throw new \UnexpectedValueException("Valor de {$key} acima do limite.");
    }
    if ($type === 'number' && !is_numeric($text)) {
      throw new \UnexpectedValueException("Valor numérico inválido em {$key}.");
    }
    // Status são conferidos pelo ScormStatusMapper em status().
    return $text;
  }

  /**
   * Deriva o estado normalizado a partir do cmi já sanitizado.
   *
   * @param array<string, mixed> $cmi
   *
   * @return array{completion: string, success: string, state: string}
   */
  private function status(string $version, array $cmi): array {
    $mapper = new ScormStatusMapper();
    if ($version === '1.2') {
      $lesson = (string) ($cmi['core']['lesson_status'] ?? 'not attempted');
      return $mapper->normalize('1.2', $lesson);
    }
    return $mapper->normalize(
      '2004',
      '',
      (string) ($cmi['completion_status'] ?? 'not attempted'),
      (string) ($cmi['success_status'] ?? 'unknown'),
    );
  }

  /**
   * Indica se a regra é uma folha (tipo e limite) e não um ramo.
   *
   * @param array<mixed> $rule
   */
  private function isLeaf(array $rule): bool {
    return count($rule) === 2 && is_string($rule[0] ?? NULL) && is_int($rule[1] ?? NULL);
  }

}
