<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Remove o título do campo de discussão (o rótulo "Comentar" que restava acima da lista).
 */
final class DiscussionFieldLabelHooks {

  /**
   * Oculta o rótulo do campo field_aculta_discussion: a lista não imprime o h2 do campo.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    if (($variables['field_name'] ?? NULL) === 'field_aculta_discussion') {
      $variables['label_hidden'] = TRUE;
    }
  }

}
