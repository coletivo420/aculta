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
 * Cursos tab. The owner is aculta_lms; the permission is create lms_course group.
 */
#[MarleyTab(
  id: 'cursos',
  label: new TranslatableMarkup('Cursos'),
  weight: 30,
)]
final class CursosTab extends MarleyTabBase {

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
      'turmas' => [
        'label' => $this->t('Turmas'),
        'weight' => 5,
        'tabs' => $this->classTabs($account) + [
          'todas' => [
            'label' => $this->t('Todas'),
            'weight' => 0,
            'listing' => ['entity' => 'group', 'bundle' => 'lms_class'],
            'actions' => [],
          ],
        ],
      ],
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


  /**
   * One tab per course the account can see; each lists only the classes linked to that course.
   *
   * @return array<string, array{label: \Stringable|string, weight: int, listing: array, actions: array}>
   */
  private function classTabs(AccountInterface $account): array {
    $tabs = [];
    $courses = $this->entityTypeManager->getStorage('group')->loadMultiple(
      $this->entityTypeManager->getStorage('group')->getQuery()->accessCheck(TRUE)->condition('type', 'lms_course')->sort('label', 'ASC')->execute(),
    );
    foreach ($courses as $course) {
      $tabs['curso-' . $course->id()] = [
        'label' => $course->label(),
        'weight' => 0,
        'listing' => ['entity' => 'group', 'bundle' => 'lms_class', 'course' => (int) $course->id()],
        'actions' => [],
      ];
    }
    return $tabs;
  }

}
