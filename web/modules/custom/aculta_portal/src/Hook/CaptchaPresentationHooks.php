<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;

/**
 * Exposes neutral presentation hooks for third-party form challenges.
 */
final class CaptchaPresentationHooks {

  /**
   * Adds a presentation contract after CAPTCHA elements have been inserted.
   */
  #[Hook('form_alter', order: new OrderAfter(modules: ['captcha']))]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $this->markChallengeElements($form);
  }

  /**
   * Recursively adds the neutral UI token without changing the form contract.
   */
  private function markChallengeElements(array &$element): void {
    if (($element['#type'] ?? NULL) === 'captcha') {
      $tokens = preg_split('/\s+/', trim((string) ($element['#attributes']['data-portal-ui'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
      $tokens[] = 'challenge-frame';
      $element['#attributes']['data-portal-ui'] = implode(' ', array_values(array_unique($tokens)));
      return;
    }

    foreach ($element as &$child) {
      if (is_array($child)) {
        $this->markChallengeElements($child);
      }
    }
  }

}
