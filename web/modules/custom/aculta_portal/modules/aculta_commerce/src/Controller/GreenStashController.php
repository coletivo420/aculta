<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
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
  ) {}

  public function page(): array {
    $provider = $this->experiences->get('green_stash');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('green_stash', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    return $provider->build();
  }

}
