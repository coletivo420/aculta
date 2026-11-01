<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Grava o instantâneo diário de situação por turma, emitido pelo cron do `aculta_lms`.
 *
 * O evento carrega só contagens (`rows`): cada linha tem curso, turma, situação e quantidade. Substitui o
 * dia inteiro da métrica, então rodar o cron de novo no mesmo dia não duplica. Sem dependência de módulo:
 * o nome do evento é texto.
 */
final class LmsClassStatusSubscriber implements EventSubscriberInterface {

  /**
   * Nome do evento (ver ClassStatusSnapshotEvent::NAME no aculta_lms).
   */
  private const SNAPSHOT = 'aculta_lms.class_status_snapshot';

  /**
   * Métrica do catálogo gravada a partir do instantâneo.
   */
  private const METRIC = 'lms.students.by_status';

  public function __construct(
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [self::SNAPSHOT => 'onSnapshot'];
  }

  /**
   * Substitui o dia de `lms.students.by_status` pelas contagens do instantâneo.
   *
   * @param object $event
   *   Evento com a propriedade `rows`: lista de curso, turma, situação e quantidade.
   */
  public function onSnapshot(object $event): void {
    $totals = [];
    foreach ($event->rows ?? [] as $row) {
      $dimension = $row['course'] . '/' . $row['class'] . '|' . $row['situation'];
      $totals[$dimension] = ($totals[$dimension] ?? 0) + (int) $row['count'];
    }
    $this->storage->replaceDay(self::METRIC, $this->storage->currentDay(), $totals);
  }

}
