<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Recurrence;

use RRule\RRule;

/**
 * Calcula as ocorrências de uma série numa janela, em leitura.
 *
 * Usa rlanvin/php-rrule. Não materializa ocorrências: regra e exceções são a
 * fonte de verdade.
 */
final class RecurrenceExpander {

  /**
   * Fuso fixo do site (decisão D9; M4).
   */
  public const FUSO = 'America/Sao_Paulo';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly RecurrenceRuleParser $parser = new RecurrenceRuleParser(),
  ) {}

  /**
   * Expande a série dentro da janela [$de, $ate].
   *
   * Exceções são chaveadas pelo início original da ocorrência (M1). Uma exceção
   * `inicio` NULL cancela a ocorrência; valor definido reagenda.
   *
   * Série aberta é limitada por $limiteSerieAberta, definida pela chamada como
   * "agora + field_limite_meses" (padrão 12).
   *
   * @param string $regra
   *   RRULE do campo `field_regra`.
   * @param \DateTimeImmutable $inicio
   *   Início da primeira ocorrência (`field_quando`).
   * @param int $duracaoSegundos
   *   Duração fixa de cada ocorrência.
   * @param \DateTimeImmutable $de
   *   Início da janela, inclusivo.
   * @param \DateTimeImmutable $ate
   *   Fim da janela, inclusivo.
   * @param \DateTimeImmutable $limiteSerieAberta
   *   Último instante para série sem COUNT nem UNTIL.
   * @param array<int, array{inicio: ?\DateTimeImmutable, fim: ?\DateTimeImmutable}> $excecoes
   *   Cancelled or rescheduled occurrence overrides.
   *   Exceções indexadas pelo timestamp do início original.
   *
   * @return array<int, array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable, original: \DateTimeImmutable, cancelada: bool}>
   *   Ocorrências em ordem. Canceladas aparecem com `cancelada` = TRUE, para o
   *   aviso na interface.
   *
   * @throws \Drupal\aculta_calendar\Recurrence\RecurrenceException
   */
  public function expandir(
    string $regra,
    \DateTimeImmutable $inicio,
    int $duracaoSegundos,
    \DateTimeImmutable $de,
    \DateTimeImmutable $ate,
    \DateTimeImmutable $limiteSerieAberta,
    array $excecoes = [],
  ): array {
    $partes = $this->parser->parse($regra);
    $fuso = new \DateTimeZone(self::FUSO);
    $inicio = $inicio->setTimezone($fuso);
    $de = $de->setTimezone($fuso);
    $ate = $ate->setTimezone($fuso);

    $temFim = isset($partes['COUNT']) || isset($partes['UNTIL']);
    $fimJanela = $ate;
    if (!$temFim && $limiteSerieAberta < $ate) {
      $fimJanela = $limiteSerieAberta->setTimezone($fuso);
    }
    if ($fimJanela < $de) {
      return [];
    }

    $rrule = new RRule($this->rfc($partes, $inicio));
    $teto = RecurrenceRuleParser::MAX_OCCURRENCES;
    $brutas = $rrule->getOccurrencesBetween($de, $fimJanela, $teto + 1);
    if (count($brutas) > $teto) {
      throw new RecurrenceException("a janela tem mais de $teto ocorrências; reduza o período");
    }

    $saida = [];
    foreach ($brutas as $data) {
      $original = \DateTimeImmutable::createFromInterface($data)->setTimezone($fuso);
      $chave = $original->getTimestamp();
      $excecao = $excecoes[$chave] ?? NULL;

      if ($excecao !== NULL && $excecao['inicio'] === NULL) {
        $saida[] = [
          'inicio' => $original,
          'fim' => $original->modify("+$duracaoSegundos seconds"),
          'original' => $original,
          'cancelada' => TRUE,
        ];
        continue;
      }

      $novoInicio = $excecao['inicio'] ?? $original;
      $novoFim = $excecao['fim'] ?? $novoInicio->modify("+$duracaoSegundos seconds");
      $saida[] = [
        'inicio' => $novoInicio->setTimezone($fuso),
        'fim' => $novoFim->setTimezone($fuso),
        'original' => $original,
        'cancelada' => FALSE,
      ];
    }

    return $saida;
  }

  /**
   * Monta o RFC 5545 da série com DTSTART no fuso do site.
   *
   * UNTIL vira o fim do dia local (23:59:59) convertido para UTC.
   *
   * @param array<string, mixed> $partes
   *   Partes normalizadas pelo parser.
   * @param \DateTimeImmutable $inicio
   *   Start date used to build DTSTART in the site's timezone.
   */
  private function rfc(array $partes, \DateTimeImmutable $inicio): string {
    $ordem = ['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'COUNT'];
    $rrule = [];
    foreach ($ordem as $chave) {
      if (!isset($partes[$chave])) {
        continue;
      }
      $valor = $partes[$chave];
      $rrule[] = $chave . '=' . (is_array($valor) ? implode(',', $valor) : $valor);
    }
    if (isset($partes['UNTIL'])) {
      // RFC 5545: com DTSTART em fuso local, UNTIL precisa ser UTC.
      $fimDoDia = new \DateTimeImmutable($partes['UNTIL'] . ' 23:59:59', new \DateTimeZone(self::FUSO));
      $rrule[] = 'UNTIL=' . $fimDoDia->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
    return 'DTSTART;TZID=' . self::FUSO . ':' . $inicio->format('Ymd\THis')
      . "\nRRULE:" . implode(';', $rrule);
  }

}
