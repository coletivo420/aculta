<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Declares Views data for ACULTA node co-authorship.
 */
final class CoAuthorViewsDataHooks {

  /**
   * Implements hook_views_data_alter().
   *
   * @param array<string, mixed> $data
   *   Views data definitions.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    if (!isset($data['node_field_data']) || !is_array($data['node_field_data'])) {
      $data['node_field_data'] = [];
    }
    /** @var array<string, mixed> $nodeFieldData */
    $nodeFieldData = $data['node_field_data'];
    $nodeFieldData['aculta_author_coauthor_name'] = [
      'title' => 'ACULTA: Autor ou coautor (autocomplete)',
      'help' => 'Filtra conteúdos onde o usuário selecionado é o autor principal ou coautor.',
      'filter' => [
        'title' => 'ACULTA: Autor ou coautor (autocomplete)',
        'help' => 'Nome do autor ou de um dos coautores cadastrados.',
        'field' => 'uid',
        'id' => 'aculta_author_coauthor_name',
      ],
    ];
    $data['node_field_data'] = $nodeFieldData;
  }

}
