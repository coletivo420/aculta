<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Occurrence;

use Drupal\aculta_calendar\Recurrence\RecurrenceExpander;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Ocorrências de uma data de evento numa janela (RECORRENCIA.md, seção 6).
 *
 * Liga `EventDate` e `EventDateOverride` ao `RecurrenceExpander`.
 * O armazenamento é UTC e a saída usa o fuso de Brasília.
 */
final class EventDateOccurrences {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RecurrenceExpander $expansor = new RecurrenceExpander(),
  ) {}

  /**
   * Ocorrências de uma data dentro da janela informada.
   *
   * Sem regra, devolve uma ocorrência quando ela cai na janela.
   * Com regra, usa o expansor.
   *
   * @param \Drupal\aculta_events\Entity\EventDate $data
   *   Data cujas ocorrências serão expandidas.
   * @param \DateTimeImmutable $de
   *   Início inclusivo da janela de consulta.
   * @param \DateTimeImmutable $ate
   *   Fim inclusivo da janela de consulta.
   * @param \DateTimeImmutable $agora
   *   Referência para o horizonte de série aberta (agora + field_limite_meses).
   *
   * @return array<int, array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable, original: \DateTimeImmutable, cancelada: bool}>
   *   Ocorrências expandidas no fuso local.
   *
   * @throws \Drupal\aculta_calendar\Recurrence\RecurrenceException
   */
  public function expandir(EventDate $data, \DateTimeImmutable $de, \DateTimeImmutable $ate, \DateTimeImmutable $agora): array {
    $fuso = new \DateTimeZone(RecurrenceExpander::FUSO);
    $item = $data->get('field_quando')->first();
    if ($item === NULL) {
      return [];
    }
    $valores = $item->getValue();
    $utc = new \DateTimeZone('UTC');
    $inicio = new \DateTimeImmutable((string) $valores['value'], $utc);
    $fim = new \DateTimeImmutable((string) $valores['end_value'], $utc);
    $duracao = $fim->getTimestamp() - $inicio->getTimestamp();

    $regra = (string) $data->get('field_regra')->value;
    if ($regra === '') {
      $ini = $inicio->setTimezone($fuso);
      if ($ini < $de || $ini > $ate) {
        return [];
      }
      $ocorrencia = [
        'inicio' => $ini,
        'fim' => $fim->setTimezone($fuso),
        'original' => $ini,
        'cancelada' => FALSE,
      ];
      return [$ocorrencia];
    }

    $limite = $agora->modify('+' . (int) ($data->get('field_limite_meses')->value ?? 12) . ' months');
    return $this->expansor->expandir(
      $regra,
      $inicio->setTimezone($fuso),
      $duracao,
      $de,
      $ate,
      $limite,
      $this->excecoes($data),
    );
  }

  /**
   * Exceções da data, indexadas pelo timestamp do início original.
   *
   * @return array<int, array{inicio: ?\DateTimeImmutable, fim: ?\DateTimeImmutable}>
   *   Exceções por início original.
   */
  private function excecoes(EventDate $data): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_override');
    $ids = $storage->getQuery()
      ->condition('field_data_pai', $data->id())
      ->accessCheck(FALSE)
      ->execute();

    $utc = new \DateTimeZone('UTC');
    $fuso = new \DateTimeZone(RecurrenceExpander::FUSO);
    $mapa = [];
    foreach ($storage->loadMultiple($ids) as $excecao) {
      if (!$excecao instanceof ContentEntityInterface) {
        continue;
      }
      $original = new \DateTimeImmutable((string) $excecao->get('field_inicio_original')->value, $utc);
      $inicio = $excecao->get('field_inicio')->value ? new \DateTimeImmutable((string) $excecao->get('field_inicio')->value, $utc) : NULL;
      $fim = $excecao->get('field_fim')->value ? new \DateTimeImmutable((string) $excecao->get('field_fim')->value, $utc) : NULL;
      $mapa[$original->getTimestamp()] = [
        'inicio' => $inicio?->setTimezone($fuso),
        'fim' => $fim?->setTimezone($fuso),
      ];
    }
    return $mapa;
  }

}
