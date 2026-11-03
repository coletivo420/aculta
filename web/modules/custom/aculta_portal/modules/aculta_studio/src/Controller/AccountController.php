<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Studio account entry, independent from the original Portal account shell.
 */
final class AccountController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
    private readonly DomainPurposeManager $domains,
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
    $build = $provider->build();
    $build['suites']['#items'] = $this->experiences->buildEntries(
      $this->account,
      $this->domains,
      ['conta', 'perfil', 'salvos', 'mercado_pago_plans'],
    );
    $cache = CacheableMetadata::createFromRenderArray($build);
    foreach ($this->experiences->all() as $candidate) {
      if (!in_array($candidate->id(), ['conta', 'perfil', 'salvos', 'mercado_pago_plans'], TRUE)) {
        $cache->addCacheableDependency($candidate->access($this->account));
      }
    }
    $cache->applyTo($build);
    return $build;
  }

}
