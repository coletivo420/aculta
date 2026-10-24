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
 * Galerias tab. The owner is aculta_gallery; the permission is create media.
 */
#[MarleyTab(
  id: 'galerias',
  label: new TranslatableMarkup('Galerias'),
  weight: 40,
)]
final class GaleriasTab extends MarleyTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_gallery');
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create media');
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    return [
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 0,
        'tabs' => [
          'nova-galeria' => [
            'label' => $this->t('Nova galeria'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Nova galeria'), 'entity.media_gallery.add_form', [], $account, 'main'),
            ],
          ],
        ],
      ],
    ];
  }

}
