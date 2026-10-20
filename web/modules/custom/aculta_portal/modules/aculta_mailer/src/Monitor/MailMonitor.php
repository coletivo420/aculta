<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\aculta_mailer\Webhook\DeliveryEventApplier;
use Drupal\aculta_mailer\Webhook\DeliveryEventType;
use Drupal\aculta_mailer\Webhook\ProviderEvent;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Monitoramento por IMAP: bounces aplicados aos envios e relatórios DMARC guardados como resumo.
 *
 * Complemento e fallback ao transporte. Duas leituras, cada uma com seu cursor por UID: bounces a cada 30 minutos e
 * DMARC a cada 12 horas (intervalos configuráveis, com piso de 15 minutos). Ver docs/MONITORING.md.
 */
final class MailMonitor {

  /**
   * Configuração não secreta do monitoramento. A senha fica em IMAP_PASSWORD (arquivo de segredos do servidor).
   */
  public const CONFIG = 'aculta_mailer.imap';

  /**
   * Credencial da senha IMAP, do contrato de segredos.
   */
  public const PASSWORD = 'IMAP_PASSWORD';

  public const BOUNCE_INTERVAL = 1800;

  public const DMARC_INTERVAL = 43200;

  /**
   * Piso de intervalo, em segundos, para não sobrecarregar a caixa.
   */
  public const MIN_INTERVAL = 900;

  /**
   * Mensagens por execução de cada leitura.
   */
  public const BATCH = 200;

  /**
   * Relatórios DMARC guardados para a tela de status.
   */
  public const REPORTS_KEPT = 30;

  private const STATE_CURSOR = 'aculta_mailer.imap.cursor';

  private const STATE_LAST_RUN = 'aculta_mailer.imap.last_run';

  private const STATE_ERROR = 'aculta_mailer.imap.last_error';

  private const STATE_REPORTS = 'aculta_mailer.imap.dmarc_reports';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly DeliveryEventApplier $applier,
    private readonly BounceParser $bounces,
    private readonly DmarcReportParser $dmarc,
    private readonly ?SecretProviderInterface $secrets,
  ) {}

  /**
   * Monitoramento ativo na configuração.
   */
  public function enabled(): bool {
    return (bool) $this->configFactory->get(self::CONFIG)->get('enabled');
  }

  /**
   * Senha IMAP configurada no arquivo de segredos (sem valor).
   */
  public function passwordConfigured(): bool {
    return $this->secrets?->value(self::PASSWORD) !== NULL;
  }

  /**
   * Cliente IMAP a partir da configuração, ou NULL quando desativado ou incompleto.
   */
  public function client(): ?ImapClientInterface {
    $config = $this->configFactory->get(self::CONFIG);
    return $this->clientFor(
      (string) $config->get('host'),
      (int) ($config->get('port') ?: 993),
      (string) $config->get('user'),
      (string) ($config->get('mailbox') ?: 'INBOX'),
      $this->secrets?->value(self::PASSWORD),
    );
  }

  /**
   * Cliente IMAP para dados informados (ex.: o teste de conexão do formulário). NULL quando algum dado falta.
   */
  public function clientFor(string $host, int $port, string $user, string $mailbox, ?string $password): ?ImapClientInterface {
    if ($host === '' || $user === '' || $password === NULL || $password === '') {
      return NULL;
    }
    return new CurlImapClient($host, $port, $user, $password, $mailbox !== '' ? $mailbox : 'INBOX');
  }

  /**
   * Senha gravada no arquivo de segredos, ou NULL.
   */
  public function storedPassword(): ?string {
    return $this->secrets?->value(self::PASSWORD);
  }

  /**
   * Execução do cron: roda cada leitura que venceu o intervalo. Falha é registrada e não interrompe o cron.
   */
  public function cron(): void {
    if (!$this->enabled()) {
      return;
    }
    $now = $this->time->getRequestTime();
    $last = $this->lastRuns();
    $due = [];
    if ($now - $last['bounce'] >= $this->interval('bounce_interval', self::BOUNCE_INTERVAL)) {
      $due[] = 'bounce';
    }
    if ($now - $last['dmarc'] >= $this->interval('dmarc_interval', self::DMARC_INTERVAL)) {
      $due[] = 'dmarc';
    }
    if ($due === []) {
      return;
    }
    $client = $this->client();
    if ($client === NULL) {
      $this->logger->warning('Monitoramento IMAP ativo, mas sem servidor, usuário ou senha configurados.');
      return;
    }
    foreach ($due as $kind) {
      try {
        $result = $kind === 'bounce' ? $this->pollBounces($client) : $this->pollDmarc($client);
        $this->logger->info('Monitoramento IMAP (@kind): @checked mensagem(ns) lida(s).', [
          '@kind' => $kind,
          '@checked' => $result['checked'],
        ]);
        $this->state->delete(self::STATE_ERROR);
      }
      catch (\Throwable $error) {
        $this->state->set(self::STATE_ERROR, [
          'kind' => $kind,
          'when' => $now,
          'message' => mb_substr($error->getMessage(), 0, 500),
        ]);
        $this->logger->warning('Monitoramento IMAP (@kind) falhou: @message', [
          '@kind' => $kind,
          '@message' => $error->getMessage(),
        ]);
      }
      $last[$kind] = $now;
    }
    $this->state->set(self::STATE_LAST_RUN, $last);
  }

  /**
   * Lê as mensagens novas da caixa e aplica as falhas de entrega aos envios correspondentes.
   *
   * @return array{checked: int, applied: int}
   *   Resultado do processamento.
   */
  public function pollBounces(ImapClientInterface $client): array {
    $uids = array_slice($client->uidsAbove($this->cursor('bounce')), 0, self::BATCH);
    $applied = 0;
    foreach ($uids as $uid) {
      $event = $this->eventFromBounce($client->fetch($uid));
      if ($event !== NULL && $this->applier->apply($event)) {
        $applied++;
      }
      $this->saveCursor('bounce', $uid);
    }
    return ['checked' => count($uids), 'applied' => $applied];
  }

  /**
   * Lê as mensagens novas da caixa e guarda o resumo dos relatórios DMARC encontrados.
   *
   * @return array{checked: int, reports: int}
   *   Resultado do processamento.
   */
  public function pollDmarc(ImapClientInterface $client): array {
    $uids = array_slice($client->uidsAbove($this->cursor('dmarc')), 0, self::BATCH);
    $found = 0;
    foreach ($uids as $uid) {
      $summary = $this->dmarc->parse($client->fetch($uid));
      if ($summary !== NULL) {
        // Os IPs de origem ficam fora do resumo guardado: só totais e a identificação do relatório.
        unset($summary['top_sources']);
        $summary['received'] = $this->time->getRequestTime();
        $reports = $this->state->get(self::STATE_REPORTS, []);
        array_unshift($reports, $summary);
        $this->state->set(self::STATE_REPORTS, array_slice($reports, 0, self::REPORTS_KEPT));
        $found++;
      }
      $this->saveCursor('dmarc', $uid);
    }
    return ['checked' => count($uids), 'reports' => $found];
  }

  /**
   * Evento de entrega a partir de uma notificação de falha, ou NULL quando não há o que aplicar.
   *
   * Código 5.x.x vira `bounced`; 4.x.x vira `deferred`. Sem o correlation ID do ACULTA, nada é aplicado.
   */
  public function eventFromBounce(string $raw): ?ProviderEvent {
    $bounce = $this->bounces->parse($raw);
    if ($bounce === NULL || ($bounce['correlation'] ?? '') === '') {
      return NULL;
    }
    if (preg_match('/^([45])\.\d{1,3}\.\d{1,3}$/', $bounce['status'], $match) !== 1) {
      return NULL;
    }
    $type = $match[1] === '5' ? DeliveryEventType::Bounced : DeliveryEventType::Deferred;
    return new ProviderEvent(
      $type,
      $bounce['correlation'],
      NULL,
      str_replace('.', '_', $bounce['status']),
      $this->time->getRequestTime(),
    );
  }

  /**
   * Última execução de cada leitura (timestamp, ou 0 quando nunca).
   *
   * @return array{bounce: int, dmarc: int}
   *   Timestamps da última leitura de cada tipo.
   */
  public function lastRuns(): array {
    $last = $this->state->get(self::STATE_LAST_RUN, []);
    return ['bounce' => (int) ($last['bounce'] ?? 0), 'dmarc' => (int) ($last['dmarc'] ?? 0)];
  }

  /**
   * Último erro de leitura, ou NULL. Sem credenciais.
   *
   * @return array{kind: string, when: int, message: string}|null
   *   Tipo da leitura, instante e mensagem segura.
   */
  public function lastError(): ?array {
    return $this->state->get(self::STATE_ERROR);
  }

  /**
   * Resumos dos relatórios DMARC recebidos, do mais recente.
   *
   * @return array<int, array<string, mixed>>
   *   Resumos, sem IPs de origem.
   */
  public function reports(): array {
    return $this->state->get(self::STATE_REPORTS, []);
  }

  /**
   * Intervalo configurado, com piso.
   */
  private function interval(string $key, int $default): int {
    $value = (int) ($this->configFactory->get(self::CONFIG)->get($key) ?: $default);
    return max(self::MIN_INTERVAL, $value);
  }

  /**
   * Último UID lido pela leitura informada (0 quando nunca).
   */
  private function cursor(string $kind): int {
    return (int) ($this->state->get(self::STATE_CURSOR, [])[$kind] ?? 0);
  }

  /**
   * Avança o cursor depois de cada mensagem, para não repetir a leitura se a execução parar no meio.
   */
  private function saveCursor(string $kind, int $uid): void {
    $cursors = $this->state->get(self::STATE_CURSOR, []);
    $cursors[$kind] = $uid;
    $this->state->set(self::STATE_CURSOR, $cursors);
  }

}
