<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Declares the public author-card field for Views.
 *
 * The handler is virtual: it reads the account of the current row and never
 * adds a column to the query. Declaring it in views_data is what allows the
 * Views plugin manager to instantiate the field instead of marking it broken.
 */
final class AuthorCardViewsDataHooks {

  /**
   * Implements hook_views_data_alter().
   *
   * @param array<string, mixed> $data
   *   Views data definitions.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['users_field_data']['aculta_user_author_card'] = [
      'title' => 'ACULTA User: cartão de autor público',
      'help' => 'Renderiza o cartão de autor de contas com perfil público explícito.',
      'field' => [
        'id' => 'aculta_user_author_card',
        'no group by' => TRUE,
      ],
    ];
  }

}
