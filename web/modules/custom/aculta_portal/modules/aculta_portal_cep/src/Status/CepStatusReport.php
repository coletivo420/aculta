<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Status;

use Drupal\aculta_portal_cep\Local\CepLocalStore;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Linhas do status das consultas de CEP: APIs (último resultado do cron) e a base local (versão e idade).
 *
 * Neutro: devolve dados e o estado (ok, attention, error), sem marcação. O Portal monta a tabela com os símbolos e
 * as classes de status já existentes (PortalRequirementsController).
 */
final class CepStatusReport {

  /** Base com até 90 dias é atual; entre 91 e 180, atenção; acima disso, erro (a base precisa ser reimportada). */
  public const LOCAL_OK_DAYS = 90;
  public const LOCAL_ATTENTION_DAYS = 180;

  /** Resultado do cron com mais de 2 dias: o cron pode estar parado. */
  public const API_STALE_SECONDS = 172800;

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.api_probe')]
    private readonly CepApiProbe $probe,
    #[Autowire(service: 'aculta_portal_cep.local_store')]
    private readonly CepLocalStore $local,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Linhas: item, valor, detalhe e status (ok, attention ou error).
   *
   * @return array<int, array{item: string, value: string, detail: string, status: string}>
   */
  public function rows(): array {
    $rows = [];
    foreach ($this->apiRows() as $row) {
      $rows[] = $row;
    }
    $rows[] = $this->localRow();
    return $rows;
  }

  /** @return array<int, array{item: string, value: string, detail: string, status: string}> */
  private function apiRows(): array {
    $last = $this->probe->last();
    $rows = [];
    foreach (['viacep', 'brasilapi'] as $id) {
      $label = $this->translation->translate('Consulta de CEP: @api', ['@api' => $id === 'viacep' ? 'ViaCEP' : 'BrasilAPI']);
      if (!isset($last[$id])) {
        $rows[] = [
          'item' => (string) $label,
          'value' => (string) $this->translation->translate('Sem verificação'),
          'detail' => (string) $this->translation->translate('Aguardando a primeira execução do cron.'),
          'status' => 'attention',
        ];
        continue;
      }
      $result = $last[$id];
      $age = $this->time->getRequestTime() - (int) $result['checked_at'];
      $stale = $age > self::API_STALE_SECONDS;
      $ok = (bool) $result['ok'];
      $status = $ok && !$stale ? 'ok' : ($ok ? 'attention' : 'error');
      $value = $ok
        ? (string) $this->translation->translate('Respondendo (HTTP @code, @ms ms)', ['@code' => (string) $result['http_status'], '@ms' => (string) $result['ms']])
        : (string) $this->translation->translate('Falhou (HTTP @code)', ['@code' => $result['http_status'] === NULL ? 'sem resposta' : (string) $result['http_status']]);
      $rows[] = [
        'item' => (string) $label,
        'value' => $value,
        'detail' => (string) $this->translation->translate('Verificado em @at (@days dias atrás).', [
          '@at' => date('d/m/Y H:i', (int) $result['checked_at']),
          '@days' => (string) (int) floor($age / 86400),
        ]) . ($stale ? ' ' . (string) $this->translation->translate('Verificação antiga: confira o cron.') : ''),
        'status' => $status,
      ];
    }
    return $rows;
  }

  /** @return array{item: string, value: string, detail: string, status: string} */
  private function localRow(): array {
    $label = (string) $this->translation->translate('Base local de CEPs');
    $manifest = $this->local->manifest();
    if ($manifest === NULL || $this->local->shardCount() === 0) {
      return [
        'item' => $label,
        'value' => (string) $this->translation->translate('Sem base importada'),
        'detail' => (string) $this->translation->translate('Importe a base com aculta-portal-cep:import (ver docs/portal/CEP-LOCAL.md).'),
        'status' => 'error',
      ];
    }
    $version = (string) $manifest['version'];
    $days = $this->daysSince($version);
    $status = match (TRUE) {
      $days === NULL => 'error',
      $days <= self::LOCAL_OK_DAYS => 'ok',
      $days <= self::LOCAL_ATTENTION_DAYS => 'attention',
      default => 'error',
    };
    $detail = $days === NULL
      ? (string) $this->translation->translate('Data da versão inválida.')
      : (string) $this->translation->translate('Versão de @date, há @days dias. @records CEPs em @shards arquivos.', [
        '@date' => date('d/m/Y', strtotime($version) ?: 0),
        '@days' => (string) $days,
        '@records' => number_format((int) ($manifest['records'] ?? 0), 0, ',', '.'),
        '@shards' => (string) $this->local->shardCount(),
      ]);
    return [
      'item' => $label,
      'value' => $version,
      'detail' => $detail,
      'status' => $status,
    ];
  }

  /** Dias entre a data da versão (Y-m-d) e hoje, ou NULL quando a data é inválida ou futura. */
  private function daysSince(string $version): ?int {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $version);
    if ($date === FALSE || $date->format('Y-m-d') !== $version) {
      return NULL;
    }
    $today = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTime(0, 0);
    $days = (int) $date->diff($today)->format('%r%a');
    return $days < 0 ? NULL : $days;
  }

}
