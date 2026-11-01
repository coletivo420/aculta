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
   * @param int|null $tipo
   *   Termo do tipo de evento opcional.
   * @param \DateTimeImmutable|null $de
   *   Data de início da janela.
   * @param \DateTimeImmutable|null $ate
   *   Data de término da janela.
   * @param \DateTimeImmutable $agora
   *   Data atual de referência.
   * @param int|null $limite
   *   Limite opcional de registros na página.
   * @param int $offset
   *   Deslocamento inicial para paginação.
   *
   * @return array<int, array{evento: \Drupal\aculta_events\Entity\Event, proxima: array}>
   *   Eventos com suas ocorrências mais próximas.
   */
  public function busca(
    ?int $tipo,
    ?\DateTimeImmutable $de,
    ?\DateTimeImmutable $ate,
    \DateTimeImmutable $agora,
    ?int $limite = NULL,
    int $offset = 0,
  ): array {
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
    $ids = $consulta->execute();
    if ($ids === []) {
      return [];
    }

    $eventos = [];
    foreach ($storage->loadMultiple($ids) as $evento) {
      if ($evento instanceof Event && $evento->access('view')) {
        $eventos[(int) $evento->id()] = $evento;
      }
    }
    if ($eventos === []) {
      return [];
    }

    // Carregamento em lote de todas as datas vinculadas, evitando N+1 queries.
    $dateStorage = $this->entityTypeManager->getStorage('aculta_event_date');
    $dateIds = $dateStorage->getQuery()
      ->condition('field_evento.target_id', array_keys($eventos), 'IN')
      ->accessCheck(FALSE)
      ->execute();

    $datasPorEvento = [];
    foreach ($dateStorage->loadMultiple($dateIds) as $data) {
      if ($data instanceof EventDate) {
        $eventoId = (int) $data->get('field_evento')->target_id;
        $datasPorEvento[$eventoId][] = $data;
      }
    }

    $resultados = [];
    foreach ($eventos as $eventoId => $evento) {
      $datas = $datasPorEvento[$eventoId] ?? [];
      $proxima = $this->primeiraOcorrenciaEmDatas($datas, $inicio, $fim, $agora);
      if ($proxima !== NULL) {
        $resultados[] = ['evento' => $evento, 'proxima' => $proxima];
      }
    }

    usort($resultados, static fn (array $a, array $b): int => $a['proxima']['inicio'] <=> $b['proxima']['inicio']);

    if ($limite !== NULL && $limite > 0) {
      return array_slice($resultados, max(0, $offset), $limite);
    }

    return $resultados;
  }

  /**
   * Primeira ocorrência de um conjunto de datas dentro do período, ou NULL.
   *
   * @param array<\Drupal\aculta_events\Entity\EventDate> $datas
   *   Datas do evento.
   * @param \DateTimeImmutable $inicio
   *   Início da janela.
   * @param \DateTimeImmutable $fim
   *   Fim da janela.
   * @param \DateTimeImmutable $agora
   *   Instante atual de referência.
   *
   * @return array|null
   *   Primeira ocorrência encontrada ou NULL.
   */
  private function primeiraOcorrenciaEmDatas(
    array $datas,
    \DateTimeImmutable $inicio,
    \DateTimeImmutable $fim,
    \DateTimeImmutable $agora,
  ): ?array {
    $primeira = NULL;
    foreach ($datas as $data) {
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
