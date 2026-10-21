<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Plugin\StudioTab;

use Drupal\aculta_studio\Attribute\StudioTab;
use Drupal\aculta_studio\StudioTabBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Projetos tab. The owner is node; the permission is create project content.
 */
#[StudioTab(
  id: 'projetos',
  label: new TranslatableMarkup('Projetos'),
  weight: 50,
)]
final class ProjetosTab extends StudioTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('node');
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create project content');
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    return [
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 0,
        'tabs' => [
          'novoprojeto' => [
            'label' => $this->t('Novo projeto'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Novo projeto'), 'node.add', ['node_type' => 'project'], $account, 'main'),
            ],
          ],
        ],
      ],
    ];
  }

}
