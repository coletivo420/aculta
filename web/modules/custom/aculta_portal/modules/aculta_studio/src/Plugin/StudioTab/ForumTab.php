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
 * Fórum tab. The owner is aculta_forum; the permission is create aculta forum posts.
 */
#[StudioTab(
  id: 'forum',
  label: new TranslatableMarkup('Fórum'),
  weight: 20,
)]
final class ForumTab extends StudioTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_forum');
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create aculta forum posts');
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    return [
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 0,
        'tabs' => [
          'novotopico' => [
            'label' => $this->t('Novo tópico'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Novo tópico'), 'aculta_forum.topic_add', [], $account, 'forum'),
            ],
          ],
        ],
      ],
    ];
  }

}
