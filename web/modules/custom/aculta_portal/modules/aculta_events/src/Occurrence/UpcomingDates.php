<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Occurrence;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Ocorrências futuras de um evento e o instante em que a saída muda (auditoria, T5).
 *
 * A página e o catálogo mostram a partir de agora. Em vez de cron invalidando cache,
 * cada saída declara max-age até o fim da próxima ocorrência listada: no instante em
 * que a data passa, o cache vence sozinho e a ocorrência sai da lista.
 *
 * Uma ocorrência em andamento continua visível: o expansor só devolve ocorrências que
 * começam na janela (RECORRENCIA.md, seção 6), então a janela é alargada pela maior
 * duração das datas do evento antes de ser pedida.
 */
final class UpcomingDates {

  /**
   * Fuso do site (D9).
   */
  public const FUSO = 'America/Sao_Paulo';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDateOccurrences $ocorrencias,
  ) {}

  /**
   * Ocorrências do evento de agora até o horizonte, em ordem de início.
   *
   * Cada item traz os horários da ocorrência e a data (`EventDate`) que a originou,
   * de onde saem local e demais campos.
   *
   * @param \Drupal\aculta_events\Entity\Event $evento
   *   Evento cujas datas serão expandidas.
   * @param \DateTimeImmutable $agora
   *   Instante de referência, em qualquer fuso.
   * @param int $horizonteDias
   *   Quantos dias à frente listar.
   *
   * @return array<int, array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable, original: \DateTimeImmutable, cancelada: bool, data: \Drupal\aculta_events\Entity\EventDate}>
   *   Ocorrências em ordem de início, cada uma com a data de origem.
   */
  public function doEvento(Event $evento, \DateTimeImmutable $agora, int $horizonteDias): array {
    $datas = $this->datas($evento);
    $de = $agora->modify(sprintf('-%d seconds', $this->maiorDuracao($datas)));
    $ate = $agora->modify(sprintf('+%d days', $horizonteDias));

    $ocorrencias = [];
    foreach ($datas as $data) {
      foreach ($this->ocorrencias->expandir($data, $de, $ate, $agora) as $ocorrencia) {
        // Já terminou: não entra na lista de próximas.
        if ($ocorrencia['fim']->getTimestamp() <= $agora->getTimestamp()) {
          continue;
        }
        $ocorrencias[] = $ocorrencia + ['data' => $data];
      }
    }
    usort($ocorrencias, static fn (array $a, array $b): int => $a['inicio'] <=> $b['inicio']);
    return $ocorrencias;
  }

  /**
   * Segundos até a saída mudar sozinha, ou NULL quando não há data futura.
   *
   * A saída muda quando a próxima ocorrência termina: ela deixa de aparecer. Datas
   * vencidas não contam; a menor validade manda.
   *
   * @param array<int, array{fim: \DateTimeImmutable}> $ocorrencias
   *   Ocorrências devolvidas por ::doEvento().
   * @param int $agora
   *   Instante de referência, em timestamp.
   *
   * @return int|null
   *   Segundos até a próxima mudança, ou NULL quando nada muda com o tempo.
   */
  public function validade(array $ocorrencias, int $agora): ?int {
    $validade = NULL;
    foreach ($ocorrencias as $ocorrencia) {
      $segundos = $ocorrencia['fim']->getTimestamp() - $agora;
      if ($segundos <= 0) {
        continue;
      }
      $validade = $validade === NULL ? $segundos : min($validade, $segundos);
    }
    return $validade;
  }

  /**
   * Fim de uma data, no fuso do site, ou NULL quando o campo está vazio.
   */
  public function fimDaData(EventDate $data): ?\DateTimeImmutable {
    $item = $data->get('field_quando')->first();
    if ($item === NULL) {
      return NULL;
    }
    $valores = $item->getValue();
    return (new \DateTimeImmutable((string) $valores['end_value'], new \DateTimeZone('UTC')))
      ->setTimezone(new \DateTimeZone(self::FUSO));
  }

  /**
   * Datas (EventDate) do evento.
   *
   * @return \Drupal\aculta_events\Entity\EventDate[]
   *   Datas do evento, sem garantir ordem.
   */
  private function datas(Event $evento): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    $ids = $storage->getQuery()
      ->condition('field_evento.target_id', $evento->id())
      ->accessCheck(FALSE)
      ->execute();
    return array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn ($data): bool => $data instanceof EventDate,
    ));
  }

  /**
   * Maior duração entre as datas do evento, em segundos.
   *
   * Alarga o início da janela para que uma ocorrência em andamento não desapareça.
   *
   * @param \Drupal\aculta_events\Entity\EventDate[] $datas
   *   Datas do evento.
   *
   * @return int
   *   Duração em segundos; zero quando não há data com intervalo.
   */
  private function maiorDuracao(array $datas): int {
    $duracao = 0;
    foreach ($datas as $data) {
      $item = $data->get('field_quando')->first();
      if ($item === NULL) {
        continue;
      }
      $valores = $item->getValue();
      $utc = new \DateTimeZone('UTC');
      $inicio = new \DateTimeImmutable((string) $valores['value'], $utc);
      $fim = new \DateTimeImmutable((string) $valores['end_value'], $utc);
      $duracao = max($duracao, $fim->getTimestamp() - $inicio->getTimestamp());
    }
    return $duracao;
  }

}
