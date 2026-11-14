<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Builds and validates the JSON contract consumed by answer forms/scoring.
 */
final class ActivityConfiguration {

  /**
   * Normalizes an educator form submission to the learner-side configuration.
   *
   * @param string $type
   *   Stable ACULTA activity type identifier.
   * @param array<string, mixed> $values
   *   Submitted configuration fields.
   *
   * @return array<string, mixed>
   *   Validated activity configuration.
   */
  public static function fromFormValues(string $type, array $values): array {
    $question = trim((string) ($values['question'] ?? ''));
    if ($question === '') {
      throw new \InvalidArgumentException('Informe o enunciado da atividade.');
    }
    $configuration = ['question' => $question];

    switch ($type) {
      case 'true_false':
        $correct = $values['correct_boolean'] ?? NULL;
        if (!in_array($correct, ['true', 'false'], TRUE)) {
          throw new \InvalidArgumentException('Escolha a resposta correta.');
        }
        $configuration['correct'] = $correct === 'true';
        break;

      case 'select':
        $options = self::uniqueLines((string) ($values['options'] ?? ''));
        $correct = self::uniqueLines((string) ($values['correct_values'] ?? ''));
        if (count($options) < 2) {
          throw new \InvalidArgumentException('Informe pelo menos duas opções diferentes.');
        }
        if ($correct === [] || array_diff($correct, $options) !== []) {
          throw new \InvalidArgumentException('Informe uma ou mais respostas corretas entre as opções cadastradas.');
        }
        $configuration['options'] = array_combine($options, $options);
        $configuration['correct'] = $correct;
        break;

      case 'fill_blanks':
        $lines = self::nonEmptyLines((string) ($values['blank_answers'] ?? ''));
        if ($lines === []) {
          throw new \InvalidArgumentException('Informe ao menos uma lacuna e suas respostas aceitas.');
        }
        $correct = [];
        foreach ($lines as $index => $line) {
          $answers = array_values(array_unique(array_filter(array_map('trim', explode('|', $line)), static fn(string $answer): bool => $answer !== '')));
          if ($answers === []) {
            throw new \InvalidArgumentException(sprintf('A lacuna %d precisa ter uma resposta aceita.', $index + 1));
          }
          $correct[$index] = $answers;
        }
        $configuration['correct'] = $correct;
        break;

      case 'free_text':
        $minimum = filter_var($values['minimum_characters'] ?? 0, FILTER_VALIDATE_INT);
        if ($minimum === FALSE || $minimum < 0 || $minimum > 10000) {
          throw new \InvalidArgumentException('O mínimo de caracteres deve ser um inteiro entre 0 e 10000.');
        }
        $configuration['minimum_characters'] = $minimum;
        break;

      case 'file_upload':
        // The answer file policy is centralized in aculta_file_uploader.
        break;

      default:
        throw new \InvalidArgumentException('Tipo de atividade não suportado.');
    }

    return $configuration;
  }

  /**
   * Parses non-empty unique lines from a textarea value.
   *
   * @return string[]
   *   Trimmed unique lines.
   */
  private static function uniqueLines(string $input): array {
    $lines = self::nonEmptyLines($input);
    if (count($lines) !== count(array_unique($lines))) {
      throw new \InvalidArgumentException('Remova opções repetidas.');
    }
    return $lines;
  }

  /**
   * Parses non-empty trimmed lines from a textarea value.
   *
   * @return string[]
   *   Trimmed non-empty lines.
   */
  private static function nonEmptyLines(string $input): array {
    return array_values(array_filter(
      array_map('trim', preg_split('/\R/u', $input) ?: []),
      static fn(string $line): bool => $line !== '',
    ));
  }

}
