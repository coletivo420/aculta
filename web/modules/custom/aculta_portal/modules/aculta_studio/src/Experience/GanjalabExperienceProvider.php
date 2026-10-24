<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_studio\Learning\LearnerDashboardComposer;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Learner experience adapter; LMS retains the dashboard data and business ACL.
 */
final class GanjalabExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly ?LearnerDashboardComposer $lmsDashboard,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'ganjalab';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'ACULTA Studio Ganjalab';
  }

  /**
   * {@inheritdoc}
   */
  public function purpose(): string {
    return 'account';
  }

  /**
   * {@inheritdoc}
   */
  public function routeName(): string {
    return 'aculta_lms.student_dashboard';
  }

  /**
   * {@inheritdoc}
   */
  public function available(): bool {
    return $this->lmsDashboard !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if (!$this->available()) {
      throw new NotFoundHttpException();
    }
    return $this->lmsDashboard->build();
  }

}
