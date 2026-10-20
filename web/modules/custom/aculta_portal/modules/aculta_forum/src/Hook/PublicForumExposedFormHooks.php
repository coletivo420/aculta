<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\views\ViewExecutable;

/**
 * Keeps the read-only public forum filter usable with global CAPTCHA enabled.
 */
final class PublicForumExposedFormHooks {

  /**
   * Removes CAPTCHA from the GET-only public topic filter form.
   *
   * The CAPTCHA module's global setting otherwise validates the Views GET
   * form during render and aborts the View query. This form only changes
   * public listing filters and cannot mutate content.
   */
  #[Hook('form_alter', order: Order::Last)]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable
      || $view->id() !== 'aculta_forum_public_topics'
      || !in_array($view->current_display, [
        'page_discussions',
        'page_new_topics',
        'page_unanswered',
        'page_most_commented',
      ], TRUE)) {
      return;
    }

    unset($form['captcha']);
  }

}
