<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/** Integrates safe ACULTA policies with the native Private Message form. */
final class PrivateMessageFormHooks {

  /** Adds rate limiting to the native create and reply form. */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($formId !== 'private_message_add_form') {
      return;
    }

    // Run after Private Message's own recipient and block validation. Keeping
    // the callback in Form API preserves its native validation and submit path.
    $form['#validate'][] = 'aculta_messenger.private_message_rate_limiter:validate';
  }

}
