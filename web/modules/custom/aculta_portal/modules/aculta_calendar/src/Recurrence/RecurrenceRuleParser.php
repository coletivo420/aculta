<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Recurrence;

/**
 * Valida e normaliza a regra de recorrência da v1 (RECORRENCIA.md, seções 5 e 7).
 *
 * Lista branca: parte desconhecida, repetida ou fora da combinação permitida é erro,
 * nunca ignorada em silêncio.
 */
final class RecurrenceRuleParser {

  /**
   * Teto de ocorrências por série e por janela (M3).
   */
  public const MAX_OCCURRENCES = 366;

  private const FREQS = ['DAILY', 'WEEKLY', 'MONTHLY'];

  private const WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

  private const CHAVES = ['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'COUNT', 'UNTIL'];

  /**
   * Valida a regra e devolve as partes normalizadas.
   *
   * @return array{FREQ: string, INTERVAL: int, BYDAY?: string[], BYMONTHDAY?: int[], COUNT?: int, UNTIL?: string}
   *   Partes normalizadas. UNTIL vem como AAAAMMDD.
   *
   * @throws \Drupal\aculta_calendar\Recurrence\RecurrenceException
   */
  public function parse(string $rule): array {
    $rule = trim($rule);
    if ($rule === '') {
      throw new RecurrenceException('regra vazia');
    }

    $partes = [];
    foreach (explode(';', $rule) as $par) {
      if (!str_contains($par, '=')) {
        throw new RecurrenceException("parte sem valor: '$par'");
      }
      [$chave, $valor] = explode('=', $par, 2);
      $chave = strtoupper(trim($chave));
      if (!in_array($chave, self::CHAVES, TRUE)) {
        throw new RecurrenceException("parte desconhecida: $chave");
      }
      if (array_key_exists($chave, $partes)) {
        throw new RecurrenceException("parte repetida: $chave");
      }
      $partes[$chave] = trim($valor);
    }

    $freq = strtoupper($partes['FREQ'] ?? '');
    if (!in_array($freq, self::FREQS, TRUE)) {
      throw new RecurrenceException('FREQ deve ser DAILY, WEEKLY ou MONTHLY');
    }

    $normalizada = ['FREQ' => $freq, 'INTERVAL' => 1];

    if (isset($partes['INTERVAL'])) {
      $intervalo = $this->inteiro($partes['INTERVAL'], 1, 99, 'INTERVAL');
      $normalizada['INTERVAL'] = $intervalo;
    }

    if (isset($partes['BYDAY'])) {
      if ($freq !== 'WEEKLY') {
        throw new RecurrenceException('BYDAY só é aceito com FREQ=WEEKLY');
      }
      $dias = array_map('strtoupper', array_map('trim', explode(',', $partes['BYDAY'])));
      foreach ($dias as $dia) {
        if (!in_array($dia, self::WEEKDAYS, TRUE)) {
          throw new RecurrenceException("BYDAY inválido: '$dia' (use MO a SU, sem prefixo numérico)");
        }
      }
      $dias = array_values(array_unique($dias));
      usort($dias, static fn (string $a, string $b): int => array_search($a, self::WEEKDAYS, TRUE) <=> array_search($b, self::WEEKDAYS, TRUE));
      $normalizada['BYDAY'] = $dias;
    }

    if (isset($partes['BYMONTHDAY'])) {
      if ($freq !== 'MONTHLY') {
        throw new RecurrenceException('BYMONTHDAY só é aceito com FREQ=MONTHLY');
      }
      $dias = [];
      foreach (explode(',', $partes['BYMONTHDAY']) as $dia) {
        $dias[] = $this->inteiro(trim($dia), 1, 31, 'BYMONTHDAY');
      }
      $dias = array_values(array_unique($dias));
      sort($dias);
      $normalizada['BYMONTHDAY'] = $dias;
    }

    if (isset($partes['COUNT']) && isset($partes['UNTIL'])) {
      throw new RecurrenceException('COUNT e UNTIL não podem vir juntos');
    }

    if (isset($partes['COUNT'])) {
      $normalizada['COUNT'] = $this->inteiro($partes['COUNT'], 1, self::MAX_OCCURRENCES, 'COUNT');
    }

    if (isset($partes['UNTIL'])) {
      $normalizada['UNTIL'] = $this->data($partes['UNTIL']);
    }

    return $normalizada;
  }

  /**
   * Devolve a regra em forma canônica, para gravar no campo `field_regra`.
   *
   * A ordem das partes é fixa, então a mesma regra sempre gera a mesma string.
   *
   * @throws \Drupal\aculta_calendar\Recurrence\RecurrenceException
   */
  public function normalize(string $rule): string {
    $p = $this->parse($rule);
    $partes = ['FREQ=' . $p['FREQ'], 'INTERVAL=' . $p['INTERVAL']];
    if (isset($p['BYDAY'])) {
      $partes[] = 'BYDAY=' . implode(',', $p['BYDAY']);
    }
    if (isset($p['BYMONTHDAY'])) {
      $partes[] = 'BYMONTHDAY=' . implode(',', $p['BYMONTHDAY']);
    }
    if (isset($p['COUNT'])) {
      $partes[] = 'COUNT=' . $p['COUNT'];
    }
    if (isset($p['UNTIL'])) {
      $partes[] = 'UNTIL=' . $p['UNTIL'];
    }
    return implode(';', $partes);
  }

  /**
   * Valida um inteiro dentro de um intervalo fechado.
   */
  private function inteiro(string $valor, int $min, int $max, string $nome): int {
    if (!preg_match('/^\d+$/', $valor)) {
      throw new RecurrenceException("$nome deve ser inteiro: '$valor'");
    }
    $numero = (int) $valor;
    if ($numero < $min || $numero > $max) {
      throw new RecurrenceException("$nome fora do intervalo $min a $max: $numero");
    }
    return $numero;
  }

  /**
   * Valida UNTIL como data AAAAMMDD real.
   */
  private function data(string $valor): string {
    if (!preg_match('/^\d{8}$/', $valor) || !checkdate((int) substr($valor, 4, 2), (int) substr($valor, 6, 2), (int) substr($valor, 0, 4))) {
      throw new RecurrenceException("UNTIL deve ser uma data AAAAMMDD válida: '$valor'");
    }
    return $valor;
  }

}
