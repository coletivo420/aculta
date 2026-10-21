<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Grava a situação de cada aluno por curso, no dia, a partir do cron do `aculta_lms`.
 *
 * Dado pessoal de classificação restrita (sensibilidade R). Substitui o dia inteiro, então rodar o cron de novo não
 * duplica. A base é o aceite único (ver docs/portal/ACEITE-UNICO.md). Sem dependência de módulo: o nome é texto.
 */
final class LmsStudentStatusSubscriber implements EventSubscriberInterface {

  /**
   * Nome do evento (ver StudentStatusSnapshotEvent::NAME no aculta_lms).
   */
  private const SNAPSHOT = 'aculta_lms.student_status_snapshot';

  /**
   * Métrica restrita gravada a partir do instantâneo.
   */
  private const METRIC = 'lms.student.status';

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
   * Substitui o dia de `lms.student.status`: uma contagem por curso, aluno e situação.
   *
   * @param object $event
   *   Evento com `rows`: curso, aluno e situação.
   */
  public function onSnapshot(object $event): void {
    $totals = [];
    foreach ($event->rows ?? [] as $row) {
      $dimension = $row['course'] . '/' . $row['uid'] . '|' . $row['situation'];
      $totals[$dimension] = ($totals[$dimension] ?? 0) + 1;
    }
    $this->storage->replaceDay(self::METRIC, $this->storage->currentDay(), $totals);
  }

}
