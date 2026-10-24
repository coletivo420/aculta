<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Studio entrypoint for the ACULTA Studio Ganjalab student experience.
 *
 * Route security and LMS entity access remain authoritative.
 */
final class GanjalabController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Renders the learner dashboard through its registered provider.
   */
  public function student(): array {
    $provider = $this->experiences->get('ganjalab');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('ganjalab', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $provider->build();
  }

  /**
   * Redirects a safe legacy request to the canonical learner dashboard.
   */
  public function legacy(): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('aculta_lms.student_dashboard')->toString(), 302);
  }

}
