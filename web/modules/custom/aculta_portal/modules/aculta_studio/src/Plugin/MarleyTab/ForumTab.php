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
 * Aba de moderação do fórum na Marley.
 *
 * O aculta_forum é dono das discussões e do acesso; o fluxo de denúncia
 * permanece com o aculta_user. A Marley expõe apenas a moderação.
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
