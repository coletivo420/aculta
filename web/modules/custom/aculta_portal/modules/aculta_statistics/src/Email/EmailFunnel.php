<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Email;

/**
 * Funil de e-mails de um dia, contado a partir das linhas do `aculta_mail_log`.
 *
 * Cada envio conta uma vez por métrica. O dia é o da solicitação (`created`) e o estado
 * é o atual: um envio confirmado depois muda o contador daquele dia quando a conciliação
 * o recalcula. `accepted` (o transporte aceitou, campo `sent`) não é `delivered` (o provedor
 * confirmou a entrega).
 *
 * Só recebe tipo, transporte, código e estado. Destinatário, correlation ID, assunto e corpo
 * não entram aqui, então nenhuma dimensão pode identificar a pessoa.
 */
final class EmailFunnel {

  public const METRIC_QUEUED = 'email.messages.queued';
  public const METRIC_ACCEPTED = 'email.messages.accepted';
  public const METRIC_DELIVERED = 'email.messages.delivered';
  public const METRIC_BOUNCED = 'email.messages.bounced';
  public const METRIC_FAILED = 'email.messages.failed';

  /**
   * Métricas produzidas por este funil.
   */
  public const METRICS = [
    self::METRIC_QUEUED,
    self::METRIC_ACCEPTED,
    self::METRIC_DELIVERED,
    self::METRIC_BOUNCED,
    self::METRIC_FAILED,
  ];

  /**
   * Tamanho máximo de uma dimensão no storage.
   */
  private const MAX_DIMENSION_LENGTH = 128;

  /**
   * Valor usado quando o registro não traz o campo.
   */
  private const UNKNOWN = 'nao_informado';

  /**
   * Conta as linhas do dia por métrica e dimensão.
   *
   * Dimensões: `queued` por tipo; `accepted`, `delivered` e `bounced` por transporte;
   * `failed` por transporte e código.
   *
   * @param iterable<array{type: string, status: string, transport: string, error_code: string, accepted: bool}> $rows
   *   Uma linha por envio, com `accepted` verdadeiro quando o campo `sent` foi preenchido.
   *
   * @return array<string, array<string, int>>
   *   Totais por métrica (chaves de METRICS) e dimensão. Métricas sem linha vêm vazias.
   */
  public static function count(iterable $rows): array {
    $counts = array_fill_keys(self::METRICS, []);
    foreach ($rows as $row) {
      $transport = self::dimension($row['transport']);
      self::add($counts, self::METRIC_QUEUED, self::dimension($row['type']));
      if ($row['accepted']) {
        self::add($counts, self::METRIC_ACCEPTED, $transport);
      }
      match ($row['status']) {
        'delivered' => self::add($counts, self::METRIC_DELIVERED, $transport),
        'bounced' => self::add($counts, self::METRIC_BOUNCED, $transport),
        'failed' => self::add($counts, self::METRIC_FAILED, self::dimension($transport . '|' . self::dimension($row['error_code']))),
        default => NULL,
      };
    }
    return $counts;
  }

  /**
   * Soma uma unidade à dimensão da métrica.
   *
   * @param array<string, array<string, int>> $counts
   *   Totais em construção, por referência.
   */
  private static function add(array &$counts, string $metric, string $dimension): void {
    $counts[$metric][$dimension] = ($counts[$metric][$dimension] ?? 0) + 1;
  }

  /**
   * Normaliza um valor para dimensão: sem `@` (cara de e-mail), limitado em tamanho, nunca vazio.
   */
  private static function dimension(string $value): string {
    $clean = trim(str_replace('@', '', $value));
    return $clean === '' ? self::UNKNOWN : substr($clean, 0, self::MAX_DIMENSION_LENGTH);
  }

}
