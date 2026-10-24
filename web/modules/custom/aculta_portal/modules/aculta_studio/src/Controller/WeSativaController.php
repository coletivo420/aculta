<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_studio\Experience\StartPageExperienceProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Account-purpose entrypoint for the Wiki contributor experience.
 */
final class WeSativaController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Renders the authorized WE SATIVA entrypoint.
   *
   * @return array<string, mixed>
   *   The contributor workspace render array.
   */
  public function page(): array {
    $provider = $this->experiences->get('we_sativa');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('we_sativa', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $provider->build();
  }

  /**
   * Renders the separate WE SATIVA start page when the owner provides it.
   *
   * @return array<string, mixed>
   *   The owner-built start page render array.
   */
  public function start(): array {
    $provider = $this->experiences->get('we_sativa');
    if ($provider === NULL || !$provider->available()
      || !$provider instanceof StartPageExperienceProviderInterface) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('we_sativa', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $provider->buildStart();
  }

}
