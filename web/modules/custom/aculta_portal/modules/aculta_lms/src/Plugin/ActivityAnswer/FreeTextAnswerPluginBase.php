<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\Component\Utility\Html;

/**
 * Shared configuration and safe phrase feedback for free-text answer plugins. */
abstract class FreeTextAnswerPluginBase implements FreeTextAnswerPluginInterface {

  public const ACTIVITY_TYPE = 'free_text';

  /**
   * {@inheritdoc}
   */
  public function activityType(): string {
    return static::ACTIVITY_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function decodeConfiguration(string $json): array {
    try {
      $root = json_decode($json === '' ? '{}' : $json, FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('Configuração da atividade inválida.', 0, $exception);
    }
    if (!$root instanceof \stdClass) {
      throw new \InvalidArgumentException('Configuração da atividade deve ser um objeto JSON.');
    }
    $decoded = (array) json_decode($json === '' ? '{}' : $json, TRUE, 512, JSON_THROW_ON_ERROR);
    if (isset($decoded['minimum_characters']) && (!is_int($decoded['minimum_characters']) || $decoded['minimum_characters'] < 0)) {
      throw new \InvalidArgumentException('O mínimo de caracteres deve ser um inteiro não negativo.');
    }
    if (isset($decoded['question']) && !is_string($decoded['question'])) {
      throw new \InvalidArgumentException('A pergunta da atividade deve ser texto.');
    }
    $phrases = $decoded['phrase_feedback'] ?? [];
    if (!is_array($phrases) || !array_is_list($phrases)) {
      throw new \InvalidArgumentException('Feedback por expressão deve ser uma lista.');
    }
    foreach ($phrases as $phrase) {
      if (!is_array($phrase) || !is_string($phrase['phrase'] ?? NULL)
        || !is_string($phrase['feedback_present'] ?? '')
        || !is_string($phrase['feedback_absent'] ?? '')) {
        throw new \InvalidArgumentException('Configuração de feedback por expressão inválida.');
      }
    }
    return $decoded;
  }

  /**
   * {@inheritdoc}
   */
  public function formativeScore(string $answer, array $configuration): float {
    $phrases = $configuration['phrase_feedback'] ?? [];
    if ($phrases === []) {
      return 100.0;
    }
    $matched = 0;
    foreach ($phrases as $item) {
      if ($this->phraseMatches($answer, (string) $item['phrase'])) {
        $matched++;
      }
    }
    return 100.0 * $matched / count($phrases);
  }

  /**
   * {@inheritdoc}
   */
  public function formativeFeedback(string $answer, array $configuration): array {
    $matched = [];
    $unmatched = [];
    foreach ($configuration['phrase_feedback'] ?? [] as $item) {
      $feedback = $this->phraseMatches($answer, (string) $item['phrase'])
        ? (string) ($item['feedback_present'] ?? '')
        : (string) ($item['feedback_absent'] ?? '');
      if ($feedback === '') {
        continue;
      }
      if ($this->phraseMatches($answer, (string) $item['phrase'])) {
        $matched[] = Html::escape($feedback);
      }
      else {
        $unmatched[] = Html::escape($feedback);
      }
    }
    $build = [];
    foreach (['matched' => $matched, 'unmatched' => $unmatched] as $kind => $messages) {
      if ($messages !== []) {
        $build[$kind] = [
          '#theme' => 'item_list',
          '#items' => array_map(static fn(string $message): array => ['#markup' => $message], $messages),
        ];
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(string $answer, array $configuration): ?string {
    $minimum = (int) ($configuration['minimum_characters'] ?? 0);
    $length = mb_strlen(trim(strip_tags($answer)));
    return $length < $minimum
      ? sprintf('A resposta precisa ter pelo menos %d caracteres; no momento tem %d.', $minimum, $length)
      : NULL;
  }

  /**
   * Indica se a resposta contém alguma das frases configuradas.
   *
   * As frases são separadas por barra vertical no valor configurado.
   *
   * @param string $answer
   *   Resposta do estudante.
   * @param string $configured
   *   Frases configuradas separadas por barra vertical.
   *
   * @return bool
   *   TRUE se alguma frase estiver presente na resposta.
   */
  private function phraseMatches(string $answer, string $configured): bool {
    foreach (explode('|', $configured) as $phrase) {
      $phrase = trim($phrase);
      if ($phrase !== '' && mb_stripos($answer, $phrase) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
