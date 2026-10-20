<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/** Applies shared image validation to LMS File Upload answer forms. */
final class SubmissionImageHooks {

  /**
   * Adds the ACULTA image constraint to the contrib managed upload element.
   *
   * Non-image extensions configured by the activity keep their native
   * validation. The constraint inspects only raster image extensions.
   */
  #[Hook('form_lms_answer_form_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['file_upload']['#upload_validators']) || !is_array($form['file_upload']['#upload_validators'])) {
      return;
    }

    $form['file_upload']['#upload_validators']['AcultaLmsSubmissionImage'] = [];
  }

}
