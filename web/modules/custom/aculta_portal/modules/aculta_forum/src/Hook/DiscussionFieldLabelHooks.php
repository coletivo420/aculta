<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Remove o título do campo de discussão (o rótulo "Comentar" que restava acima da lista).
 */
final class DiscussionFieldLabelHooks {

  /**
   * Oculta o rótulo do campo field_aculta_discussion: a lista não imprime o h2 do campo.
   */
  #[Hook('preprocess_field', order: Order::Last)]
  public function preprocessField(array &$variables): void {
    if (str_contains((string) ($variables['field_name'] ?? ''), 'discussion')) {
      \Drupal::logger('aculta_debug')->notice('DBG field=@f hidden_before=@h', ['@f' => (string) ($variables['field_name'] ?? 'none'), '@h' => var_export($variables['label_hidden'] ?? null, TRUE)]);
    }
    if (($variables['field_name'] ?? NULL) === 'field_aculta_discussion') {
      $variables['label_hidden'] = TRUE;
    }
  }

}
