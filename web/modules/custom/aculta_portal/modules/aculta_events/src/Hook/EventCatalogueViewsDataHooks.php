<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Exposes narrowly scoped Views handlers for the public event catalogue. */
final class EventCatalogueViewsDataHooks {

  /**
   * Adds a render field and an order field backed by catalogue request data.
   *
   * @param array<string, mixed> $data
   *   Views data definitions.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['aculta_event']['aculta_events_catalogue_card'] = [
      'title' => 'Cartão do catálogo público de Eventos',
      'help' => 'Renderiza o cartão com dados já filtrados pelo catálogo de Eventos.',
      'field' => [
        'id' => 'aculta_events_catalogue_card',
        'no group by' => TRUE,
      ],
    ];
    $data['aculta_event']['aculta_events_catalogue_order'] = [
      'title' => 'Ordem do catálogo público de Eventos',
      'help' => 'Preserva a ordem de próxima ocorrência definida pelo catálogo.',
      'sort' => [
        'id' => 'aculta_events_catalogue_order',
        'no group by' => TRUE,
      ],
    ];
  }

}
