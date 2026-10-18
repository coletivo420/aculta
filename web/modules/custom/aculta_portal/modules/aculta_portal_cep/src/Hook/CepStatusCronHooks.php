<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Hook;

use Drupal\aculta_portal_cep\Status\CepApiProbe;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Verifica as APIs de CEP no cron, para o status do Portal ter um resultado recente. */
final class CepStatusCronHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.api_probe')]
    private readonly CepApiProbe $probe,
  ) {}

  #[Hook('cron')]
  public function cron(): void {
    // Diária: o probe só consulta as APIs quando a última verificação tem mais de um dia.
    $this->probe->checkIfDue();
  }

}
