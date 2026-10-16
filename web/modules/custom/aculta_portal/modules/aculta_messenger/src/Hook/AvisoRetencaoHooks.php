<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Aviso\AvisoRetencao;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Exclui automaticamente os avisos do Fórum com mais de seis meses (ACULTA Messenger, regra de retenção).
 *
 * Só toca a entidade `aculta_aviso` com tipo de Fórum. Avisos persistentes e alertas do sistema administrativo
 * não passam por aqui.
 */
final class AvisoRetencaoHooks {

  /** Avisos removidos por execução do cron, para não sobrecarregar uma rodada. */
  private const LOTE = 500;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
  ) {}

  /** Implements hook_cron(). */
  #[Hook('cron')]
  public function expirarAvisos(): void {
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('tipo', AvisoRetencao::TIPOS, 'IN')
      ->condition('created', AvisoRetencao::limite((int) $this->time->getRequestTime()), '<=')
      ->sort('created', 'ASC')
      ->range(0, self::LOTE)
      ->execute();
    if ($ids !== []) {
      $storage->delete($storage->loadMultiple($ids));
    }
  }

}
