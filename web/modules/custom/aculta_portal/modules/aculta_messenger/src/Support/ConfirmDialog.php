<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Support;

use Drupal\Component\Render\MarkupInterface;

/**
 * An action that asks first: a menu button opens the Drupal modal (Drupal.dialog), and its "Sim" button runs the action.
 *
 * The question is a hidden container identified by data-aculta-dialog. The theme's script opens it with Drupal.dialog and
 * keeps it inside the form, so "Sim" still submits. No presentation classes or dialog size are set in this module.
 */
final class ConfirmDialog {

  /**
   * Adds the open button and the dialog to a form.
   *
   * @param array<string, mixed> $form
   *   The form being built.
   * @param string $name
   *   Action name: the confirm button posts it, and the dialog is identified by it (data-aculta-dialog).
   * @param \Drupal\Component\Render\MarkupInterface|string $openLabel
   *   The menu button label.
   * @param \Drupal\Component\Render\MarkupInterface|string $question
   *   The question the dialog asks.
   * @param \Drupal\Component\Render\MarkupInterface|string $confirmLabel
   *   The confirm button label ("Sim").
   * @param \Drupal\Component\Render\MarkupInterface|string $cancelLabel
   *   The cancel button label ("Cancelar").
   * @param int $weight
   *   Position of the open button among the menu actions.
   *
   * @return array<string, mixed>
   *   The form with the open button in "actions" and the dialog under "$name_dialog".
   */
  public static function add(array $form, string $name, MarkupInterface|string $openLabel, MarkupInterface|string $question, MarkupInterface|string $confirmLabel, MarkupInterface|string $cancelLabel, int $weight = 0): array {
    $form['actions'][$name . '_open'] = [
      '#type' => 'button',
      '#button_type' => 'button',
      '#value' => $openLabel,
      '#weight' => $weight,
      '#attributes' => ['data-portal-ui' => 'conversation-action', 'data-aculta-dialog-open' => $name],
    ];
    // The dialog's parts live in one container, so their order is local to it (two dialogs in one form do not mix).
    // The container starts hidden; Drupal.dialog shows it. The buttons stay registered by Form API.
    $form[$name . '_dialog'] = [
      '#type' => 'container',
      '#attributes' => ['data-aculta-dialog' => $name, 'hidden' => 'hidden'],
      'question' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'p',
        '#value' => $question,
        '#weight' => -5,
      ],
      'actions' => [
        '#type' => 'actions',
        '#weight' => 0,
        $name => [
          '#type' => 'submit',
          '#name' => $name,
          '#value' => $confirmLabel,
          '#attributes' => ['data-portal-ui' => 'conversation-action'],
        ],
        $name . '_cancel' => [
          '#type' => 'button',
          '#button_type' => 'button',
          '#value' => $cancelLabel,
          '#attributes' => ['data-portal-ui' => 'conversation-action', 'data-aculta-dialog-close' => ''],
        ],
      ],
    ];
    return $form;
  }

}
