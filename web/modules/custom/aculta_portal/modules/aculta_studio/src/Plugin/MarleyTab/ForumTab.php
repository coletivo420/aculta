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
 * Fórum moderation entry. aculta_forum owns discussions and access; the
 * report workflow remains with aculta_user. Marley exposes moderation only.
 */
#[MarleyTab(
  id: 'forum',
  label: new TranslatableMarkup('Moderação do Fórum'),
  weight: 20,
)]
final class ForumTab extends MarleyTabBase {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_forum');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'moderar denuncias do forum');
  }

  /**
   * {@inheritdoc}
   */
  public function sections(AccountInterface $account): array {
    return [
      'moderacao' => [
        'label' => $this->t('Moderação'),
        'weight' => 0,
        'tabs' => [
          'denuncias' => [
            'label' => $this->t('Denúncias'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Denúncias do fórum'), 'aculta_user.denuncias_forum', [], $account, 'main'),
            ],
          ],
        ],
      ],
    ];
  }

}
