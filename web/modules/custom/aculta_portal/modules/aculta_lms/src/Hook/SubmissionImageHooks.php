<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Applies the Portal upload policy to LMS File Upload answer forms.
 */
final class SubmissionImageHooks {

  use StringTranslationTrait;

  /**
   * The Portal policy is optional at container level, as in other LMS hooks.
   *
   * The aculta_lms module requires aculta_portal in production, so the cap
   * always applies there.
   */
  public function __construct(
    TranslationInterface $stringTranslation,
    private readonly ?UserUploadPolicy $uploadPolicy = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Caps the contrib managed upload element to the LMS upload policy.
   *
   * The activity still chooses its extensions, but only inside the consumer
   * policy from aculta_portal. The ACULTA image constraint then inspects raster
   * image bytes; other extensions keep their native validation.
   */
  #[Hook('form_lms_answer_form_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['file_upload']['#upload_validators']) || !is_array($form['file_upload']['#upload_validators'])) {
      return;
    }

    $validators = $form['file_upload']['#upload_validators'];
    $validators['AcultaLmsSubmissionImage'] = [];
    if ($this->uploadPolicy !== NULL) {
      $validators = $this->uploadPolicy->restrictValidators($validators, 'lms');
      // The help text lists what is accepted, not what the activity asked for.
      $form['file_upload']['#description'] = $this->t('Envie um arquivo. Extensões permitidas: @extensions.', [
        '@extensions' => $this->uploadPolicy->acceptedExtensions($validators),
      ]);
    }
    $form['file_upload']['#upload_validators'] = $validators;
  }

}
