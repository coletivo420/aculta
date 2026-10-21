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
 * Revista tab: creating editorial publications. The owner is aculta_magazine.
 */
#[StudioTab(
  id: 'revista',
  label: new TranslatableMarkup('Revista'),
  weight: 10,
)]
final class RevistaTab extends StudioTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_magazine');
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create revista content');
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    return [
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 0,
        'tabs' => [
          'novapublicacao' => [
            'label' => $this->t('Nova publicação'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Nova publicação'), 'node.add', ['node_type' => 'revista'], $account, 'magazine'),
            ],
          ],
        ],
      ],
    ];
  }

}
