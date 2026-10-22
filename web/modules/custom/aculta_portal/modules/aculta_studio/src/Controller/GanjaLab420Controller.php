<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Studio entrypoint for the GanjaLab420 student experience.
 *
 * Route security and LMS entity access remain authoritative.
 */
final class GanjaLab420Controller {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
  ) {}

  public function student(): array {
    $provider = $this->experiences->get('ganjalab420');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('ganjalab420', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $provider->build();
  }

}
