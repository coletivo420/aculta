<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Connections;

use Drupal\aculta_user\Social\ConnectionQueryService;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Composes the Connections block for the Studio Dashboard.
 */
class StudioDashboardConnectionsComposer {

  public function __construct(
    protected readonly ?ConnectionQueryService $queryService,
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * Composes the connections block render array.
   *
   * @return array<string, mixed>
   *   The component render array.
   */
  public function compose(): array {
    $connections = [];
    if ($this->queryService !== NULL && $this->currentUser->isAuthenticated()) {
      $result = $this->queryService->getAcceptedConnections((int) $this->currentUser->id(), limit: 3, offset: 0);
      foreach ($result['items'] as $item) {
        $connections[] = [
          'name' => $item['name'],
          'profile_url' => '/u/' . $item['name'],
          'avatar_url' => NULL,
          'avatar_alt' => $item['name'],
        ];
      }
    }

    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:studio-connections-block',
      '#props' => [
        'title' => 'CONEXÕES',
        'connections' => $connections,
        'empty_message' => 'Você ainda não tem conexões',
        'find_connections_label' => 'Encontrar conexões',
        'find_connections_url' => Url::fromRoute('aculta_studio.account_connections')->toString(),
        'all_connections_label' => 'Ver todas',
        'all_connections_url' => Url::fromRoute('aculta_studio.account_connections')->toString(),
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['aculta_friendship_list'],
      ],
    ];
  }

}
