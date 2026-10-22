<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Alters the Revista publication forms.
 *
 * The secondary properties are grouped and collapsed, and the form warns
 * before leaving with unsaved changes. The form stays the owner's: Form API,
 * access, CSRF and workflow are untouched.
 */
final class RevistaFormHooks {

  // Form ids of the publication forms (create and edit).
  private const FORM_IDS = ['node_revista_form', 'node_revista_edit_form'];

  // Fields and properties that support the publication, outside the main flow.
  private const SECONDARY = [
    'field_tags',
    'field_references',
    'field_related_activity',
    'field_credits',
    'field_meta_tags',
    'field_published_at',
    'path',
    'url_redirects',
    'simple_sitemap',
    'menu',
  ];

  // Key of the group that hosts the secondary properties.
  private const GROUP = 'revista_secondary';

  /**
   * Groups the secondary properties and prepares the validation feedback.
   */
  #[Hook('form_alter')]
  public function alter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, self::FORM_IDS, TRUE)) {
      return;
    }
    $form['#attached']['library'][] = 'aculta_magazine/revista-form';

    $secondary = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Propriedades secundárias'),
      '#description' => new TranslatableMarkup('Etiquetas, projeto, referências, SEO, endereço e agendamento.'),
      '#open' => FALSE,
      '#weight' => 90,
      '#attributes' => ['data-aculta-revista' => 'secondary'],
    ];
    $moved = 0;
    foreach (self::SECONDARY as $key) {
      if (isset($form[$key]) && is_array($form[$key])) {
        $secondary[$key] = $form[$key];
        unset($form[$key]);
        $moved++;
      }
    }
    // Nothing was moved: do not render an empty group.
    if ($moved === 0) {
      return;
    }

    $errors = $form_state->getErrors();
    if ($this->hasErrorBelow(self::GROUP, $errors)) {
      // A closed group hides the field that failed validation. Open it so the
      // message and the field stay visible without JavaScript.
      $secondary['#open'] = TRUE;
      // Only take the focus when the group owns the first error of the form;
      // an earlier invalid field keeps the default reading order.
      if ($this->firstErrorStartsAt(self::GROUP, $errors)) {
        $this->focusFirstInvalid($secondary, $errors, [self::GROUP]);
      }
    }
    $form[self::GROUP] = $secondary;
  }

  /**
   * Checks whether an error was recorded on the group or on one of its fields.
   */
  private function hasErrorBelow(string $path, array $errors): bool {
    foreach (array_keys($errors) as $key) {
      $key = (string) $key;
      if ($key === $path || str_starts_with($key, $path . '][')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether the first recorded error belongs to the given path.
   */
  private function firstErrorStartsAt(string $path, array $errors): bool {
    if ($errors === []) {
      return FALSE;
    }
    $first = (string) array_key_first($errors);
    return $first === $path || str_starts_with($first, $path . '][');
  }

  /**
   * Marks the first invalid field with autofocus, descending to its input.
   *
   * The attribute works without JavaScript. A group that is still closed would
   * hide the element, which is why this only runs after the group is opened.
   *
   * @param array $element
   *   Form element being inspected, by reference.
   * @param array $errors
   *   Errors recorded in the form state, keyed by element parents.
   * @param string[] $parents
   *   Parents of the element in the form tree.
   *
   * @return bool
   *   TRUE when an element received the focus.
   */
  private function focusFirstInvalid(array &$element, array $errors, array $parents): bool {
    if (($element['#access'] ?? TRUE) === FALSE) {
      return FALSE;
    }
    if (!$this->hasErrorBelow(implode('][', $parents), $errors)) {
      return FALSE;
    }
    foreach (Element::children($element) as $child) {
      if ($this->focusFirstInvalid($element[$child], $errors, array_merge($parents, [$child]))) {
        return TRUE;
      }
    }
    // The error belongs to the field container itself (a required widget, for
    // example): the focus goes to the first input inside it.
    return $this->focusFirstInput($element);
  }

  /**
   * Focuses the first visible input, skipping hidden values and tokens.
   */
  private function focusFirstInput(array &$element): bool {
    if (($element['#access'] ?? TRUE) === FALSE) {
      return FALSE;
    }
    if (isset($element['#type']) && Element::children($element) === []) {
      if (in_array($element['#type'], ['hidden', 'value', 'token'], TRUE)) {
        return FALSE;
      }
      $element['#attributes']['autofocus'] = 'autofocus';
      return TRUE;
    }
    foreach (Element::children($element) as $child) {
      if ($this->focusFirstInput($element[$child])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
