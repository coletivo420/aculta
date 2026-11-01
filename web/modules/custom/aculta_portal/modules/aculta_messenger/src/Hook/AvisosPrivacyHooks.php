<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_messenger\Form\AvisosPrivacyForm;

/**
 * Acrescenta o bloco "Avisos" à página de privacidade da conta (ACULTA Messenger, F5.2).
 */
final class AvisosPrivacyHooks {

  public function __construct(
    private readonly AvisosPrivacyForm $form,
  ) {}

  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($formId !== 'aculta_user_profile_visibility_form') {
      return;
    }
    $this->form->build($form);
    $form['#submit'][] = 'aculta_messenger.avisos_privacy_form:submit';
  }

}
