<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

/**
 * ACULTA contract for free-text answer behavior and formative feedback. */
interface FreeTextAnswerPluginInterface {

  /**
   * Retorna o tipo estável de CourseActivity tratado por este plugin.
   *
   * @return string
   *   Identificador do tipo de atividade.
   */
  public function activityType(): string;

  /**
   * Decodifica a configuração JSON do plugin.
   *
   * @param string $json
   *   Configuração codificada em JSON.
   *
   * @return array<string, mixed>
   *   Configuração decodificada.
   */
  public function decodeConfiguration(string $json): array;

  /**
   * Returns a formative score from 0 to 100; it is never a final grade.
   *
   * @param array<string, mixed> $configuration
   *   Validated configuration returned by decodeConfiguration().
   */
  public function formativeScore(string $answer, array $configuration): float;

  /**
   * Builds escaped formative feedback for the submitted answer.
   *
   * @param array<string, mixed> $configuration
   *   Validated configuration returned by decodeConfiguration().
   *
   * @return array<string, array<string, mixed>>
   *   Render arrays grouped by matched and unmatched feedback.
   */
  public function formativeFeedback(string $answer, array $configuration): array;

  /**
   * Returns an error message when the answer is too short, otherwise NULL.
   *
   * @param array<string, mixed> $configuration
   *   Validated configuration returned by decodeConfiguration().
   */
  public function validate(string $answer, array $configuration): ?string;

}
