<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Hook;

use Drupal\aculta_portal_cep\Status\CepApiProbe;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs scheduled checks for the configured CEP APIs.
 *
 * The Portal status display reads the results saved by this hook.
 */
final class CepStatusCronHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.api_probe')]
    private readonly CepApiProbe $probe,
  ) {}

  /**
   * Runs the provider check when its interval has elapsed.
   */
  #[Hook('cron')]
  public function cron(): void {
    // The probe only calls APIs when the last check is over one day old.
    $this->probe->checkIfDue();
  }

}
