<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Hook;

use Drupal\aculta_image\Audit\ImageAudit;
use Drupal\aculta_image\ImageCleanup;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cron do aculta_image: calcula impressões digitais visuais em lotes pequenos (fase M9). Não altera imagens.
 */
final class CronHooks {

  /** Imagens por execução do cron, para não sobrecarregar CPU e memória. */
  private const LOTE = 200;

  public function __construct(
    #[Autowire(service: 'aculta_image.audit')]
    private readonly ImageAudit $audit,
    #[Autowire(service: 'aculta_image.cleanup')]
    private readonly ImageCleanup $cleanup,
  ) {}

  /** Implements hook_cron(). */
  #[Hook('cron')]
  public function calcularImpressoes(): void {
    $this->audit->calcularImpressoes(self::LOTE);
    $this->cleanup->run(100);
  }

}
