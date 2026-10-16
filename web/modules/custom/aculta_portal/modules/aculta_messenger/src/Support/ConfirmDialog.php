<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Support;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;

/**
 * An action that asks first: a menu button opens a native dialog, and its "Sim" button runs the action.
 *
 * The dialog carries only its data attribute (data-aculta-dialog) for the theme's script and styles. No presentation
 * classes are set in this module.
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
    // One id per dialog on the page: the list builds one menu per conversation, and each needs its own title.
    $titleId = Html::getUniqueId('aculta-' . $name . '-dialog-title');
    $form['actions'][$name . '_open'] = [
      '#type' => 'button',
      '#button_type' => 'button',
      '#value' => $openLabel,
      '#weight' => $weight,
      '#attributes' => ['data-portal-ui' => 'conversation-action', 'data-aculta-dialog-open' => $name],
    ];
    // The dialog's parts live in one container, so their order is local to it (two dialogs in one form do not mix).
    // The open and close tags are inline templates: a #prefix is filtered as admin HTML (it drops <dialog>), and a theme
    // wrapper did not receive the children of a form container here. The buttons stay registered by Form API.
    $form[$name . '_dialog'] = [
      '#type' => 'container',
      'open' => [
        '#type' => 'inline_template',
        '#template' => '<dialog data-aculta-dialog="' . $name . '" aria-labelledby="' . $titleId . '">',
        '#weight' => -10,
      ],
      'question' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'p',
        '#element_attributes' => new Attribute(['id' => $titleId]),
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
      'close' => [
        '#type' => 'inline_template',
        '#template' => '</dialog>',
        '#weight' => 10,
      ],
    ];
    return $form;
  }

}
