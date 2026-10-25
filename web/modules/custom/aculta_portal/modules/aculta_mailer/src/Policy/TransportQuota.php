<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Policy;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Limite de envio por transporte (modelo "Rate Limiting" do WP Mail SMTP), em janelas de hora, dia, semana e mês.
 *
 * Duas fontes de esgotamento: a contagem do próprio registro (mensagens aceitas na janela) e um sinal do provedor
 * (texto de cota ou limite no erro ou no bounce, ver QuotaSignal). Com qualquer uma, o transporte sai de uso até o fim
 * do sinal, e a mensagem segue pelo transporte de contingência.
 *
 * Configuração: `aculta_mailer.policy:rate_limit.<transporte>.<janela>` (hour, day, week, month). A chave `quota.<transporte>`
 * continua valendo como limite diário.
 */
final class TransportQuota {

  /**
   * Janelas suportadas, em segundos.
   */
  public const INTERVALS = [
    'hour' => 3600,
    'day' => 86400,
    'week' => 604800,
    'month' => 2592000,
  ];

  /**
   * Duração do sinal do provedor, em segundos (um dia, como a cota diária do WP Mail SMTP).
   */
  public const SIGNAL_WINDOW = 86400;

  private const STATE_SIGNAL = 'aculta_mailer.quota_signal.';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly TimeInterface $time,
    private readonly StateInterface $state,
  ) {}

  /**
   * Indica se o transporte está esgotado agora: sinal do provedor, ou alguma janela com o limite atingido.
   */
  public function exhausted(string $transport): bool {
    if ($this->signalled($transport)) {
      return TRUE;
    }
    foreach ($this->limits($transport) as $interval => $cap) {
      if ($this->used($transport, self::INTERVALS[$interval]) >= $cap) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Registra o sinal do provedor: o transporte fica fora de uso pela janela do sinal.
   */
  public function signal(string $transport): void {
    $this->state->set(self::STATE_SIGNAL . $transport, $this->time->getRequestTime() + self::SIGNAL_WINDOW);
  }

  /**
   * Limites configurados por janela (só valores positivos).
   *
   * @return array<string, int>
   *   Limite por janela (hour, day, week, month).
   */
  public function limits(string $transport): array {
    $config = $this->configFactory->get('aculta_mailer.policy');
    $limits = [];
    $rate = $config->get('rate_limit.' . $transport);
    foreach (is_array($rate) ? $rate : [] as $interval => $cap) {
      if (isset(self::INTERVALS[$interval]) && is_int($cap) && $cap > 0) {
        $limits[$interval] = $cap;
      }
    }
    $daily = $config->get('quota.' . $transport);
    if (is_int($daily) && $daily > 0 && !isset($limits['day'])) {
      $limits['day'] = $daily;
    }
    return $limits;
  }

  /**
   * Sinal do provedor ainda válido.
   */
  private function signalled(string $transport): bool {
    return (int) $this->state->get(self::STATE_SIGNAL . $transport, 0) > $this->time->getRequestTime();
  }

  /**
   * Mensagens aceitas pelo transporte nos últimos segundos. Só `sent` e `delivered` consomem a cota do provedor.
   */
  private function used(string $transport, int $seconds): int {
    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    return (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('transport', $transport)
      ->condition('status', ['sent', 'delivered'], 'IN')
      ->condition('created', $this->time->getRequestTime() - $seconds, '>=')
      ->count()
      ->execute();
  }

}
