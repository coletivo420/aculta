<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Studio entrypoint for the ACULTA Studio Ganjalab student experience.
 *
 * Route security and LMS entity access remain authoritative.
 */
final class GanjalabController {

  use StringTranslationTrait;

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
    if (!$this->experiences->access('ganjalab', 'courses', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $build = $provider->build();

    // Keep the educator workspace distinct, but make the alternate task
    // discoverable to accounts whose route access allows course creation.
    $educatorUrl = Url::fromRoute('aculta_lms.educator_dashboard');
    if ($educatorUrl->access($this->account)) {
      $build['educator_workspace'] = PortalLinkElement::build(
        $this->t('Ganjalab Educador'),
        $educatorUrl,
        [
          'data-aculta-studio' => 'student-to-educator',
          'data-portal-ui' => 'studio-action',
        ],
      );
      $build['educator_workspace']['#weight'] = -90;
    }

    return $build;
  }

}
