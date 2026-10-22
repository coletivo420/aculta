<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Learner experience adapter; LMS retains the dashboard data and business ACL.
 */
final class GanjaLab420ExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly ?LearningDashboardController $lmsDashboard,
  ) {}

  public function id(): string {
    return 'ganjalab420';
  }

  public function label(): string {
    return 'GanjaLab420';
  }

  public function purpose(): string {
    return 'account';
  }

  public function routeName(): string {
    return 'aculta_lms.student_dashboard';
  }

  public function available(): bool {
    return $this->lmsDashboard !== NULL;
  }

  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->cachePerUser();
  }

  public function build(): array {
    if (!$this->available()) {
      throw new NotFoundHttpException();
    }
    return $this->lmsDashboard->student();
  }

}
