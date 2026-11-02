<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Scorm;

/**
 * Normaliza o estado reportado por um SCO SCORM 1.2 ou 2004.
 *
 * Função pura: recebe os valores que o conteúdo informou e devolve o estado
 * normalizado do ACULTA. Não lê nem grava progresso; onde o resultado será
 * guardado é decisão de produto pendente, e esta classe não decide isso.
 *
 * Saída:
 * - completion: 'not_attempted', 'incomplete' ou 'completed'.
 * - success: 'unknown', 'passed' ou 'failed'.
 * - state: 'not_started', 'in_progress', 'completed', 'passed' ou 'failed'.
 *
 * Em SCORM 1.2, `lesson_status` combina conclusão e sucesso: passed e failed
 * implicam conclusão, e completed não informa sucesso.
 */
final class ScormStatusMapper {

  /**
   * Valores aceitos de cmi.core.lesson_status (SCORM 1.2).
   */
  private const LESSON_STATUS_12 = [
    'passed' => ['completed', 'passed'],
    'failed' => ['completed', 'failed'],
    'completed' => ['completed', 'unknown'],
    'incomplete' => ['incomplete', 'unknown'],
    'browsed' => ['incomplete', 'unknown'],
    'not attempted' => ['not_attempted', 'unknown'],
  ];

  /**
   * Valores aceitos de cmi.completion_status (SCORM 2004).
   */
  private const COMPLETION_STATUS_2004 = [
    'completed' => 'completed',
    'incomplete' => 'incomplete',
    'not attempted' => 'not_attempted',
    'unknown' => 'unknown',
  ];

  /**
   * Valores aceitos de cmi.success_status (SCORM 2004).
   */
  private const SUCCESS_STATUS_2004 = ['passed', 'failed', 'unknown'];

  /**
   * Normaliza os valores de um SCO.
   *
   * @param string $version
   *   '1.2' ou '2004'.
   * @param string $lessonStatus
   *   Valor de lesson_status (SCORM 1.2). Ignorado em 2004.
   * @param string|null $completionStatus
   *   Valor de completion_status (SCORM 2004). Ignorado em 1.2.
   * @param string|null $successStatus
   *   Valor de success_status (SCORM 2004). Ignorado em 1.2.
   *
   * @return array{completion: string, success: string, state: string}
   *
   * @throws \UnexpectedValueException
   *   Quando a versão ou algum valor não existe na especificação.
   */
  public function normalize(string $version, string $lessonStatus = '', ?string $completionStatus = NULL, ?string $successStatus = NULL): array {
    if ($version === '1.2') {
      $key = strtolower(trim($lessonStatus));
      if (!isset(self::LESSON_STATUS_12[$key])) {
        throw new \UnexpectedValueException('lesson_status desconhecido no SCORM 1.2.');
      }
      [$completion, $success] = self::LESSON_STATUS_12[$key];
    }
    elseif ($version === '2004') {
      $completionKey = strtolower(trim((string) $completionStatus));
      $successKey = strtolower(trim((string) $successStatus));
      if (!isset(self::COMPLETION_STATUS_2004[$completionKey])) {
        throw new \UnexpectedValueException('completion_status desconhecido no SCORM 2004.');
      }
      if (!in_array($successKey, self::SUCCESS_STATUS_2004, TRUE)) {
        throw new \UnexpectedValueException('success_status desconhecido no SCORM 2004.');
      }
      $completion = self::COMPLETION_STATUS_2004[$completionKey];
      $success = $successKey;
    }
    else {
      throw new \UnexpectedValueException('Versão SCORM desconhecida.');
    }

    return [
      'completion' => $completion,
      'success' => $success,
      'state' => $this->state($completion, $success),
    ];
  }

  /**
   * Deriva o estado normalizado a partir de conclusão e sucesso.
   */
  private function state(string $completion, string $success): string {
    if ($success === 'passed') {
      return 'passed';
    }
    if ($success === 'failed') {
      return 'failed';
    }
    if ($completion === 'completed') {
      return 'completed';
    }
    if ($completion === 'incomplete') {
      return 'in_progress';
    }
    return 'not_started';
  }

}
