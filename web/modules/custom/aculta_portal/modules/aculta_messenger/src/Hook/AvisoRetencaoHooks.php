<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Aviso\AvisoRetencao;
use Drupal\aculta_messenger\Database\ForumCommentNoticeSchema;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Exclui automaticamente os avisos vencidos do Fórum (regra de retenção).
 *
 * Só toca a entidade `aculta_aviso` com tipo de Fórum. Avisos persistentes e
 * alertas administrativos não passam por aqui.
 */
final class AvisoRetencaoHooks {

  /**
   * Avisos removidos por execução do cron, para não sobrecarregar uma rodada.
   */
  private const LOTE = 500;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
  ) {}

  /**
   * Implements hook_cron().
   */
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

    $schema = $this->database->schema();
    $table = ForumCommentNoticeSchema::TABLE;
    if (!$schema->tableExists($table)) {
      return;
    }
    $expired_event_ids = $this->database->select($table, 'event')
      ->fields('event', ['id'])
      ->condition('created', AvisoRetencao::limite((int) $this->time->getRequestTime()), '<=')
      ->orderBy('created', 'ASC')
      ->range(0, self::LOTE)
      ->execute()
      ->fetchCol();
    if ($expired_event_ids !== []) {
      $this->database->delete($table)
        ->condition('id', $expired_event_ids, 'IN')
        ->execute();
    }
  }

}
