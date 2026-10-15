<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/** Integrates safe ACULTA policies with the native Private Message form. */
final class PrivateMessageFormHooks {

  /** Enforces plain text and ACULTA send policies on native PM forms. */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if (!str_starts_with($formId, 'private_message_add_form')) {
      return;
    }

    // Messenger is intentionally plain text. Lock both the visible widget
    // definition and the submitted value; hiding a selector alone is not a
    // server-side format restriction.
    if (isset($form['message']['widget'][0]) && ($form['message']['widget'][0]['#type'] ?? NULL) === 'text_format') {
      $form['message']['widget'][0]['#format'] = 'plain_text';
      $form['message']['widget'][0]['#allowed_formats'] = ['plain_text'];
      $form['message']['#weight'] = 0;
      $form['actions']['#weight'] = 10;
      if (isset($form['message']['widget'][0]['format']['format'])) {
        $form['message']['widget'][0]['format']['format']['#access'] = FALSE;
        $form['message']['widget'][0]['format']['format']['#value'] = 'plain_text';
        $form['message']['widget'][0]['format']['format']['#default_value'] = 'plain_text';
      }
      // The native placeholder is the English word "Message"; the site's composer says what to write.
      // The text_format element copies its #placeholder to the textarea in #process, so set it on the source.
      $form['message']['widget'][0]['#placeholder'] = new TranslatableMarkup('Escreva sua mensagem');
      $form['#after_build'][] = 'aculta_messenger.private_message_form_validator:hidePlainTextGuidelines';
      $form['#validate'][] = 'aculta_messenger.private_message_form_validator:enforcePlainTextFormat';
    }

    // The native submit is the single reply action. Replies say "Responder";
    // a new conversation says "Enviar mensagem".
    $form['actions']['submit']['#value'] = $formState->get('thread') instanceof PrivateMessageThreadInterface
      ? new TranslatableMarkup('Responder')
      : new TranslatableMarkup('Enviar mensagem');

    // Run after Private Message's own recipient and block validation. Keeping
    // the callback in Form API preserves its native validation and submit path.
    $form['#validate'][] = 'aculta_messenger.private_message_form_validator:validateBlockedRecipients';
    $form['#validate'][] = 'aculta_messenger.private_message_form_validator:validateNewConversationPrivacy';
    $form['#validate'][] = 'aculta_messenger.private_message_form_validator:validateRateLimit';
  }

}
