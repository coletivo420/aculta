<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Studio account entry, independent from the original Portal account shell.
 */
final class AccountController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Shows the authorized account experience without rewriting Portal forms.
   *
   * @return array<string, mixed>
   *   The owner's account entrypoint render array.
   */
  public function page(): array {
    $provider = $this->experiences->get('conta');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('conta', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $provider->build();
  }

}
