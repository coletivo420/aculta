<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Account-purpose entrypoint for the personal Studio experience.
 */
final class PersonalController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Renders the personal provider after rechecking availability and access.
   *
   * @return array<string, mixed>
   *   Authorized personal entry render array.
   */
  public function page(): array {
    $provider = $this->experiences->get('pessoal');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('pessoal', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $provider->build();
  }

}
