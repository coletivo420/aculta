<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Plugin\StudioTab;

use Drupal\aculta_studio\Attribute\StudioTab;
use Drupal\aculta_studio\StudioTabBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

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
  public function actions(AccountInterface $account): array {
    return [
      [
        'title' => $this->t('Nova publicação'),
        'url' => Url::fromRoute('node.add', ['node_type' => 'revista']),
        'access' => $this->access($account)->isAllowed(),
      ],
    ];
  }

}
