<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Plugin\MarleyTab;

use Drupal\aculta_studio\Attribute\MarleyTab;
use Drupal\aculta_studio\MarleyTabBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Revista tab.
 *
 * The owner is aculta_magazine; the states come from the editorial workflow
 * `revista`. Publicações lists the nodes by state (Todos, Rascunhos, Em
 * revisão, Publicados, Arquivados) through the listing of the Studio, which
 * keeps the node access of the owner. Editorial operations resolve on the main
 * domain, as the Core marks every node operation route as an admin route; the
 * Magazine keeps the route contract and the Studio consumes it. Criar keeps
 * the create route.
 */
#[MarleyTab(
  id: 'revista',
  label: new TranslatableMarkup('Revista'),
  weight: 10,
)]
final class RevistaTab extends MarleyTabBase {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_magazine');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create revista content')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'edit any revista content'));
  }

  /**
   * {@inheritdoc}
   */
  public function sections(AccountInterface $account): array {
    $states = [
      'todos' => ['label' => $this->t('Meus conteúdos'), 'state' => NULL],
      'rascunhos' => ['label' => $this->t('Rascunhos'), 'state' => 'draft'],
      'em-revisao' => ['label' => $this->t('Em revisão'), 'state' => 'review'],
      'publicados' => ['label' => $this->t('Publicados'), 'state' => 'published'],
      'arquivados' => ['label' => $this->t('Arquivados'), 'state' => 'archived'],
    ];
    $listTabs = [];
    foreach ($states as $id => $item) {
      $listTabs[$id] = [
        'label' => $item['label'],
        'weight' => count($listTabs),
        'listing' => [
          'bundle' => 'revista',
          'scope' => 'mine',
          'state' => $item['state'],
          'editable' => TRUE,
        ],
        'actions' => [],
      ];
    }
    return [
      'publicacoes' => [
        'label' => $this->t('Meu trabalho'),
        'weight' => 0,
        'tabs' => $listTabs,
      ],
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 10,
        'tabs' => [
          'nova-publicacao' => [
            'label' => $this->t('Nova publicação'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Nova publicação'), 'node.add', ['node_type' => 'revista'], $account, 'main'),
            ],
          ],
        ],
      ],
    ];
  }

}
