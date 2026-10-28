<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Catalogue;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Busca do catálogo: eventos publicados com ocorrência no período (F3).
 *
 * Rascunho não entra. Cancelado entra com aviso, como no modelo. Períodos
 * chegam como datas de Brasília; a saída é em ordem de primeira ocorrência.
 */
final class EventCatalogue {

  /**
   * Horizonte padrão quando o período não tem fim: um ano a partir de agora.
   */
  private const HORIZONTE_DIAS = 365;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDateOccurrences $ocorrencias,
  ) {}

  /**
   * Eventos com ocorrência no período, cada um com a primeira ocorrência.
   *
   * @return array<int, array{evento: \Drupal\aculta_events\Entity\Event, proxima: array}>
   *   Eventos com suas ocorrências mais próximas.
   */
  public function busca(?int $tipo, ?\DateTimeImmutable $de, ?\DateTimeImmutable $ate, \DateTimeImmutable $agora): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $inicio = $de !== NULL && $de > $agora ? $de->setTimezone($fuso) : $agora->setTimezone($fuso);
    $fim = $ate !== NULL ? $ate->setTimezone($fuso)->setTime(23, 59, 59) : $agora->modify('+' . self::HORIZONTE_DIAS . ' days');
    if ($fim < $inicio) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('aculta_event');
    $consulta = $storage->getQuery()
      ->condition('field_status', ['publicado', 'cancelado'], 'IN')
      ->accessCheck(FALSE);
    if ($tipo !== NULL) {
      $consulta->condition('field_tipo.target_id', $tipo);
    }
    $resultados = [];
    foreach ($storage->loadMultiple($consulta->execute()) as $evento) {
      if (!$evento->access('view')) {
        continue;
      }
      $proxima = $this->primeiraOcorrencia($evento, $inicio, $fim, $agora);
      if ($proxima !== NULL) {
        $resultados[] = ['evento' => $evento, 'proxima' => $proxima];
      }
    }
    usort($resultados, static fn (array $a, array $b): int => $a['proxima']['inicio'] <=> $b['proxima']['inicio']);
    return $resultados;
  }

  /**
   * Primeira ocorrência de qualquer data do evento dentro do período, ou NULL.
   */
  private function primeiraOcorrencia(Event $evento, \DateTimeImmutable $inicio, \DateTimeImmutable $fim, \DateTimeImmutable $agora): ?array {
    $ids = $this->entityTypeManager->getStorage('aculta_event_date')->getQuery()
      ->condition('field_evento.target_id', $evento->id())
      ->accessCheck(FALSE)
      ->execute();
    $primeira = NULL;
    foreach ($this->entityTypeManager->getStorage('aculta_event_date')->loadMultiple($ids) as $data) {
      if (!$data instanceof EventDate) {
        continue;
      }
      foreach ($this->ocorrencias->expandir($data, $inicio, $fim, $agora) as $ocorrencia) {
        if ($primeira === NULL || $ocorrencia['inicio'] < $primeira['inicio']) {
          $primeira = $ocorrencia;
        }
      }
    }
    return $primeira;
  }

}
