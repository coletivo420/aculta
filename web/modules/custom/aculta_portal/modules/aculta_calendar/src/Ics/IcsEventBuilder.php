<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Ics;

/**
 * Monta um calendário iCalendar (RFC 5545) com um único VEVENT.
 *
 * Escrito do zero a partir da regra do RFC 5545. Não contém trecho copiado de
 * outro projeto.
 */
final class IcsEventBuilder {

  /**
   * Quebra de linha exigida pelo RFC 5545 (§3.1).
   */
  private const EOL = "\r\n";

  /**
   * Octetos por linha física antes da dobra (RFC 5545 §3.1).
   */
  private const LINE_LIMIT = 75;

  /**
   * Fuso dos horários do ACULTA: horário de Brasília.
   */
  private const TZID = 'America/Sao_Paulo';

  /**
   * Construtor.
   *
   * @param string $uidDomain
   *   Domínio do UID. Compõe o identificador do evento.
   */
  public function __construct(
    private readonly string $uidDomain,
  ) {}

  /**
   * Monta o calendário com um evento.
   *
   * @param string $key
   *   Chave estável da entidade, por exemplo "node:123". Compõe o UID junto
   *   com a data de início, então o mesmo evento na mesma data mantém o UID.
   * @param string $summary
   *   Título. CR e LF viram espaço; demais controles são removidos antes do escape.
   * @param string $location
   *   Local. Recebe o mesmo tratamento do título. Vazio omite a propriedade.
   * @param \DateTimeImmutable $start
   *   Início. Em dia inteiro, só a data é usada.
   * @param \DateTimeImmutable|null $end
   *   Fim. Em dia inteiro é exclusivo; se nulo, é o dia seguinte ao início.
   *   Em evento com horário, se nulo, DTEND é omitido.
   * @param bool $allDay
   *   TRUE gera DTSTART e DTEND com VALUE=DATE.
   * @param \DateTimeImmutable $stamp
   *   Momento da geração. DTSTAMP sai em UTC.
   *
   * @return string
   *   Conteúdo do arquivo .ics, com CRLF e linhas dobradas.
   */
  public function build(
    string $key,
    string $summary,
    string $location,
    \DateTimeImmutable $start,
    ?\DateTimeImmutable $end,
    bool $allDay,
    \DateTimeImmutable $stamp,
  ): string {
    return $this->buildSerie($key, $summary, $location, $start, $end, $allDay, $stamp);
  }

  /**
   * Calendário com uma série: evento mestre com RRULE, EXDATE e reagendamentos (RECURRENCE-ID).
   *
   * Decisão M5 (RECORRENCIA.md): a série sai como regra, sem expandir ocorrências no arquivo.
   *
   * @param string|null $rrule
   *   RRULE normalizada (FREQ=...;...). UNTIL em AAAAMMDD vira fim do dia de Brasília, em UTC.
   * @param \DateTimeImmutable[] $excecoes
   *   Inícios originais cancelados (EXDATE).
   * @param array<int, array{original: \DateTimeImmutable, inicio: \DateTimeImmutable, fim: ?\DateTimeImmutable}> $reagendadas
   *   Ocorrências com novo horário (VEVENT com RECURRENCE-ID).
   */
  public function buildSerie(
    string $key,
    string $summary,
    string $location,
    \DateTimeImmutable $start,
    ?\DateTimeImmutable $end,
    bool $allDay,
    \DateTimeImmutable $stamp,
    ?string $rrule = NULL,
    array $excecoes = [],
    array $reagendadas = [],
  ): string {
    $uid = $this->uid($key, $start, $allDay);
    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//ACULTA//Calendario//PT',
      'CALSCALE:GREGORIAN',
      // Brasília não tem horário de verão desde 2019: deslocamento fixo.
      'BEGIN:VTIMEZONE',
      'TZID:' . self::TZID,
      'BEGIN:STANDARD',
      'DTSTART:19700101T000000',
      'TZOFFSETFROM:-0300',
      'TZOFFSETTO:-0300',
      'END:STANDARD',
      'END:VTIMEZONE',
    ];
    $lines = array_merge($lines, $this->vevent($uid, $summary, $location, $start, $end, $allDay, $stamp, NULL, $rrule, $excecoes));
    foreach ($reagendadas as $reagendada) {
      $lines = array_merge($lines, $this->vevent($uid, $summary, $location, $reagendada['inicio'], $reagendada['fim'], $allDay, $stamp, $reagendada['original'], NULL, []));
    }
    $lines[] = 'END:VCALENDAR';

    $folded = array_map(fn (string $line): string => $this->fold($line), $lines);
    return implode(self::EOL, $folded) . self::EOL;
  }

  /**
   * Calendário com várias séries (exportação de período). Cada item: chaves do buildSerie().
   *
   * @param array<int, array{key: string, summary: string, location: string, start: \DateTimeImmutable, end: ?\DateTimeImmutable, allDay: bool, stamp: \DateTimeImmutable, rrule?: ?string, excecoes?: array, reagendadas?: array}> $series
   */
  public function buildMany(array $series): string {
    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//ACULTA//Calendario//PT',
      'CALSCALE:GREGORIAN',
      'BEGIN:VTIMEZONE',
      'TZID:' . self::TZID,
      'BEGIN:STANDARD',
      'DTSTART:19700101T000000',
      'TZOFFSETFROM:-0300',
      'TZOFFSETTO:-0300',
      'END:STANDARD',
      'END:VTIMEZONE',
    ];
    foreach ($series as $serie) {
      $uid = $this->uid($serie['key'], $serie['start'], $serie['allDay']);
      $lines = array_merge($lines, $this->vevent(
        $uid, $serie['summary'], $serie['location'], $serie['start'], $serie['end'], $serie['allDay'],
        $serie['stamp'], NULL, $serie['rrule'] ?? NULL, $serie['excecoes'] ?? [],
      ));
      foreach ($serie['reagendadas'] ?? [] as $reagendada) {
        $lines = array_merge($lines, $this->vevent(
          $uid, $serie['summary'], $serie['location'], $reagendada['inicio'], $reagendada['fim'], $serie['allDay'],
          $serie['stamp'], $reagendada['original'], NULL, [],
        ));
      }
    }
    $lines[] = 'END:VCALENDAR';
    $folded = array_map(fn (string $line): string => $this->fold($line), $lines);
    return implode(self::EOL, $folded) . self::EOL;
  }

  /**
   * Linhas de um VEVENT. Com $recorrencia, é a ocorrência reagendada de uma série.
   *
   * @return string[]
   */
  private function vevent(string $uid, string $summary, string $location, \DateTimeImmutable $start, ?\DateTimeImmutable $end, bool $allDay, \DateTimeImmutable $stamp, ?\DateTimeImmutable $recorrencia, ?string $rrule, array $excecoes): array {
    $lines = ['BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . $this->utc($stamp)];

    if ($recorrencia !== NULL) {
      $lines[] = $allDay
        ? 'RECURRENCE-ID;VALUE=DATE:' . $recorrencia->format('Ymd')
        : 'RECURRENCE-ID;TZID=' . self::TZID . ':' . $this->local($recorrencia);
    }

    if ($allDay) {
      $end ??= $start->modify('+1 day');
      $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
      $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
    }
    else {
      $lines[] = 'DTSTART;TZID=' . self::TZID . ':' . $this->local($start);
      if ($end !== NULL) {
        $lines[] = 'DTEND;TZID=' . self::TZID . ':' . $this->local($end);
      }
    }

    if ($rrule !== NULL && $rrule !== '') {
      $lines[] = 'RRULE:' . $this->rruleRfc($rrule, $allDay);
    }
    foreach ($excecoes as $excecao) {
      $lines[] = $allDay
        ? 'EXDATE;VALUE=DATE:' . $excecao->format('Ymd')
        : 'EXDATE;TZID=' . self::TZID . ':' . $this->local($excecao);
    }

    $lines[] = 'SUMMARY:' . $this->escape($this->clean($summary));
    if ($location !== '') {
      $lines[] = 'LOCATION:' . $this->escape($this->clean($location));
    }
    $lines[] = 'END:VEVENT';
    return $lines;
  }

  /**
   * RRULE pronta para o arquivo. UNTIL em data vira fim do dia local, em UTC (ou data, em dia inteiro).
   */
  private function rruleRfc(string $rrule, bool $allDay): string {
    $partes = [];
    foreach (explode(';', $rrule) as $parte) {
      if (str_starts_with($parte, 'UNTIL=')) {
        $data = substr($parte, 6);
        if ($allDay) {
          $partes[] = 'UNTIL=' . $data;
          continue;
        }
        $fim = new \DateTimeImmutable($data . ' 23:59:59', new \DateTimeZone(self::TZID));
        $partes[] = 'UNTIL=' . $fim->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        continue;
      }
      $partes[] = $parte;
    }
    return implode(';', $partes);
  }

  /**
   * UID estável: hash de chave e data de início, com o domínio.
   */
  private function uid(string $key, \DateTimeImmutable $start, bool $allDay): string {
    $when = $allDay ? $start->format('Ymd') : $this->utc($start);
    return substr(hash('sha256', $key . '|' . $when), 0, 32) . '@' . $this->uidDomain;
  }

  /**
   * Data e hora em UTC, no formato de DATE-TIME com sufixo Z (usado em DTSTAMP).
   */
  private function utc(\DateTimeImmutable $moment): string {
    return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
  }

  /**
   * Data e hora no horário de Brasília, sem sufixo (o TZID leva o fuso).
   */
  private function local(\DateTimeImmutable $moment): string {
    return $moment->setTimezone(new \DateTimeZone(self::TZID))->format('Ymd\THis');
  }

  /**
   * Troca CR e LF por espaço e remove os demais controles (RFC 5545 §3.3.11).
   *
   * Assim uma quebra de linha no título não cria propriedade nova, e as
   * palavras continuam separadas.
   */
  private function clean(string $text): string {
    // CRLF conta como uma quebra só, então vira um espaço só.
    $semQuebras = str_replace(["\r\n", "\r", "\n"], ' ', $text);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $semQuebras) ?? '';
  }

  /**
   * Escapa barra invertida, ponto e vírgula e vírgula (RFC 5545 §3.3.11).
   *
   * A barra vem primeiro, para não escapar duas vezes os caracteres seguintes.
   */
  private function escape(string $text): string {
    return str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $text);
  }

  /**
   * Dobra a linha em 75 octetos; continuação começa com espaço (RFC 5545 §3.1).
   *
   * O corte nunca separa uma sequência UTF-8.
   */
  private function fold(string $line): string {
    if (strlen($line) <= self::LINE_LIMIT) {
      return $line;
    }

    $parts = [];
    $first = TRUE;
    while ($line !== '') {
      // A continuação leva um espaço inicial, que conta no limite.
      $limit = $first ? self::LINE_LIMIT : self::LINE_LIMIT - 1;
      $prefix = $first ? '' : ' ';
      if (strlen($line) <= $limit) {
        $parts[] = $prefix . $line;
        break;
      }
      $cut = $limit;
      // Recua enquanto o byte seguinte for continuação UTF-8 (10xxxxxx).
      while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
        $cut--;
      }
      $parts[] = $prefix . substr($line, 0, $cut);
      $line = substr($line, $cut);
      $first = FALSE;
    }

    return implode(self::EOL, $parts);
  }

}
