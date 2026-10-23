<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_commerce\Navigation\GreenStashNavigation;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrypoint for the independent Green Stash Studio experience.
 */
final class GreenStashController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
    private readonly GreenStashNavigation $navigation,
  ) {}

  public function page(string $section = 'overview'): array {
    $provider = $this->experiences->get('green_stash');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('green_stash', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    if (!isset(GreenStashNavigation::AREAS[$section])) {
      throw new NotFoundHttpException();
    }
    return $this->navigation->build($section);
  }

}
