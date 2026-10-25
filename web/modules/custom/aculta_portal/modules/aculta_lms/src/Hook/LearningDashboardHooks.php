<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the learning dashboard to the shared account navigation.
 */
final class LearningDashboardHooks {

  /**
   * Adds the dashboard section for authenticated account holders.
   */
  #[Hook('aculta_portal_account_sections_alter')]
  public function addStudentDashboard(array &$sections, AccountInterface $account): void {
    if ($account->isAnonymous()) {
      return;
    }
    $sections['aculta_lms.student_dashboard'] = [
      'title' => new TranslatableMarkup('Aprendizagem'),
      'route' => 'aculta_lms.student_dashboard',
      'icon' => 'mortarboard',
    ];
  }

}
