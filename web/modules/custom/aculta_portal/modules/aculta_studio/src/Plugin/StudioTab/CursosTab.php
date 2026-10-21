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
 * Cursos tab. The owner is aculta_lms; the permission is create lms_course group.
 */
#[StudioTab(
  id: 'cursos',
  label: new TranslatableMarkup('Cursos'),
  weight: 30,
)]
final class CursosTab extends StudioTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_lms');
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create lms_course group');
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    return [
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 0,
        'tabs' => [
          'novo-curso' => [
            'label' => $this->t('Novo curso'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Novo curso'), 'entity.group.add_form', ['group_type' => 'lms_course'], $account, 'courses'),
            ],
          ],
        ],
      ],
    ];
  }

}
