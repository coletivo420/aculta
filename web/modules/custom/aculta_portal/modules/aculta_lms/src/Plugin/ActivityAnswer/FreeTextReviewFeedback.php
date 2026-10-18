<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lms\Attribute\ActivityAnswer;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\Answer;
use Drupal\lms\Plugin\ActivityAnswerBase;

/**
 * Free-text plugin with formative feedback and educator-controlled grading.
 *
 * Adapted from Drupal LMS 1.2.4's FreeTextFeedback plugin. The upstream
 * implementation is GPL-2.0-or-later. This variant retains phrase-based
 * feedback for practice but leaves the final score pending educator review.
 */
#[ActivityAnswer(
  id: 'free_text_review_feedback',
  name: new TranslatableMarkup('Resposta aberta com feedback formativo e avaliação do educador'),
)]
final class FreeTextReviewFeedback extends ActivityAnswerBase {

  use \Drupal\lms_answer_plugins\Plugin\WithFeedbackPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluatedOnSave(Answer $activity): bool {
    // Phrase matching is only formative; only an educator assigns the score.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore(Answer $answer): float {
    $activity = $answer->getActivity();
    $data = $answer->getData();

    // If no answer has been provided yet, return 0.
    if (!\array_key_exists('answer', $data) || $data['answer'] === '') {
      return 0.0;
    }

    $student_answer = $data['answer'];
    $phrases_feedback = $activity->get('phrase_feedback');

    $total_phrases = $phrases_feedback->count();
    // At least one phrase is required but this protects us from divide-by-zero.
    if ($total_phrases === 0) {
      // If no match phrases were provided, any answer gets full score.
      return 1.0;
    }

    $matched_phrases = 0;
    foreach ($phrases_feedback as $item) {
      if ($this->isPhraseFound($student_answer, $item->get('phrase')->getString())) {
        $matched_phrases++;
      }
    }

    // Calculate score based on percentage of matched phrases.
    return (float) ($matched_phrases / $total_phrases);
  }

  /**
   * {@inheritdoc}
   */
  public function answeringForm(array &$form, FormStateInterface $form_state, Answer $answer): void {
    $data = $answer->getData();
    $activity = $answer->getActivity();
    $min_chars_field = $activity->get('minimum_characters');
    $minimum_characters = !$min_chars_field->isEmpty() ? (int) $min_chars_field->first()->getValue()['value'] : 0;

    $form['answer'] = [
      '#title' => $this->t('Sua resposta'),
      '#type' => 'textarea',
      '#default_value' => \array_key_exists('answer', $data) ? $data['answer'] : '',
      '#rows' => 10,
      '#required' => TRUE,
      '#element_validate' => [[$this, 'validateAnswer']],
      '#minimum_characters' => $minimum_characters,
    ];

    $this->addFeedbackElementsToAnsweringForm($form, $form_state, $answer);
  }

  /**
   * {@inheritdoc}
   */
  private function buildFeedbackRenderArray(bool $is_correct, Answer $answer, ActivityInterface $activity): array {
    $feedback = [];
    $current_answer = $answer->getData()['answer'] ?? '';

    // Check the answer for required phrases and build feedback accordingly.
    $has_matched = FALSE;
    $has_unmatched = FALSE;
    foreach ($activity->get('phrase_feedback') as $delta => $item) {
      $phrase = $item->get('phrase')->getString();

      if ($this->isPhraseFound($current_answer, $phrase)) {
        $feedback_text = $item->get('feedback_present')->getString();
        if ($feedback_text !== '') {
          if (!$has_matched) {
            $feedback['content']['matched'] = [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#attributes' => ['class' => ['feedback-section', 'matched-phrases']],
              'heading' => [
                '#type' => 'html_tag',
                '#tag' => 'h4',
                '#value' => $this->t('Você demonstrou compreensão sobre:'),
                '#weight' => -10,
              ],
            ];
            $has_matched = TRUE;
          }
          $feedback['content']['matched']['items'][$delta] = [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['phrase-match', self::CLASS_CORRECT]],
            '#value' => $feedback_text,
          ];
        }
      }
      else {
        $feedback_text = $item->get('feedback_absent')->getString();
        if ($feedback_text !== '') {
          if (!$has_unmatched) {
            $feedback['content']['unmatched'] = [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#attributes' => ['class' => ['feedback-section', 'unmatched-phrases']],
              'heading' => [
                '#type' => 'html_tag',
                '#tag' => 'h4',
                '#value' => $this->t('Você pode revisar estes pontos:'),
                '#weight' => -10,
              ],
            ];
            $has_unmatched = TRUE;
          }
          $feedback['content']['unmatched']['items'][$delta] = [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['phrase-mismatch', self::CLASS_WRONG]],
            '#value' => $feedback_text,
          ];
        }
      }
    }
    return $feedback;
  }

  /**
   * {@inheritdoc}
   */
  private function addAnswerClassesToForm(array &$form, bool $is_correct, Answer $answer): void {
    // This plugin has no form elements to add classes to.
  }

  /**
   * Element validate callback for minimum character validation.
   */
  public function validateAnswer(array &$element, FormStateInterface $form_state): void {
    $minimum_characters = $element['#minimum_characters'];
    if ($minimum_characters === 0) {
      return;
    }

    $value = \strip_tags($element['#value']);
    $value = \trim($value);
    $value = \preg_replace('/\r\n?/', '', $value);
    $total = \mb_strlen($value);

    if ($total < $minimum_characters) {
      $args = [
        '@min_message' => $this->formatPlural($minimum_characters, 'A resposta precisa ter pelo menos @count caractere;', 'A resposta precisa ter pelo menos @count caracteres;'),
        '@total_message' => $this->formatPlural($total, 'no momento tem @count caractere.', 'no momento tem @count caracteres.'),
      ];
      $form_state->setError($element, $this->t('@min_message @total_message', $args));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evaluationDisplay(Answer $answer): array {
    $data = $answer->getData();
    $answer_markup = '';
    if (\array_key_exists('answer', $data)) {
      $answer_markup = \nl2br(Html::escape($data['answer']));
    }

    return [
      'activity' => $this->entityTypeManager->getViewBuilder('lms_activity')->view($answer->getActivity(), 'activity'),
      'answer' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Resposta do estudante'),
        'answer' => [
          '#markup' => $answer_markup,
        ],
      ],
    ];
  }

  /**
   * Checks if a phrase is found in the student's answer.
   */
  private function isPhraseFound(string $student_answer, string $phrase_with_variants): bool {
    $phrase_variants = \explode('|', Html::escape($phrase_with_variants));
    foreach ($phrase_variants as $phrase) {
      $phrase = \trim($phrase);
      if ($phrase === '') {
        continue;
      }
      if (\stripos($student_answer, $phrase) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
