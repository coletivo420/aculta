<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;

/**
 * Integrates safe ACULTA policies with the native Private Message form.
 */
final class PrivateMessageFormHooks {

  /**
   * Enforces plain text and ACULTA send policies on native PM forms.
   *
   * @param array<array-key, mixed> $form
   *   O valor informado para $form.
   * @param FormStateInterface $formState
   *   O valor informado para $formState.
   * @param string $formId
   *   O valor informado para $formId.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if (!str_starts_with($formId, 'private_message_add_form')) {
      return;
    }

    // Messenger is intentionally plain text. Lock both the visible widget
    // definition and the submitted value; hiding a selector alone is not a
    // server-side format restriction.
    if (isset($form['message']) && is_array($form['message'])) {
      $message = &$form['message'];
      if (isset($message['widget']) && is_array($message['widget'])) {
        $messageWidget = &$message['widget'];
        if (isset($messageWidget[0]) && is_array($messageWidget[0]) && ($messageWidget[0]['#type'] ?? NULL) === 'text_format') {
          $messageItem = &$messageWidget[0];
          $messageItem['#format'] = 'plain_text';
          $messageItem['#allowed_formats'] = ['plain_text'];
          $message['#weight'] = 0;
          if (isset($form['actions']) && is_array($form['actions'])) {
            $form['actions']['#weight'] = 10;
          }
          if (isset($messageItem['format']) && is_array($messageItem['format'])) {
            $format = &$messageItem['format'];
            if (isset($format['format']) && is_array($format['format'])) {
              $formatSub = &$format['format'];
              $formatSub['#access'] = FALSE;
              $formatSub['#value'] = 'plain_text';
              $formatSub['#default_value'] = 'plain_text';
            }
          }
          // Replace the native English "Message" placeholder with the site's
          // composer text. The text_format process copies this to its textarea.
          $messageItem['#placeholder'] = new TranslatableMarkup('Escreva sua mensagem');
          if (!isset($form['#after_build']) || !is_array($form['#after_build'])) {
            $form['#after_build'] = [];
          }
          $form['#after_build'][] = 'aculta_messenger.private_message_form_validator:hidePlainTextGuidelines';
          if (!isset($form['#validate']) || !is_array($form['#validate'])) {
            $form['#validate'] = [];
          }
          $form['#validate'][] = 'aculta_messenger.private_message_form_validator:enforcePlainTextFormat';
        }
      }
    }

    // The native submit is the single reply action. Replies say "Responder";
    // a new conversation says "Enviar mensagem".
    if (isset($form['actions']) && is_array($form['actions'])) {
      $actions = &$form['actions'];
      if (isset($actions['submit']) && is_array($actions['submit'])) {
        $actions['submit']['#value'] = $formState->get('thread') instanceof PrivateMessageThreadInterface
          ? new TranslatableMarkup('Responder')
          : new TranslatableMarkup('Enviar mensagem');
      }
    }

    // Run after Private Message's own recipient and block validation. Keeping
    // the callback in Form API preserves its native validation and submit path.
    if (!isset($form['#validate']) || !is_array($form['#validate'])) {
      $form['#validate'] = [];
    }
    $form['#validate'][] = 'aculta_messenger.private_message_form_validator:validateBlockedRecipients';
    $form['#validate'][] = 'aculta_messenger.private_message_form_validator:validateNewConversationPrivacy';
    $form['#validate'][] = 'aculta_messenger.private_message_form_validator:validateRateLimit';
    if (!isset($form['#submit']) || !is_array($form['#submit'])) {
      $form['#submit'] = [];
    }
    $form['#submit'][] = 'aculta_messenger.private_message_form_validator:registerRateLimit';
  }

}
