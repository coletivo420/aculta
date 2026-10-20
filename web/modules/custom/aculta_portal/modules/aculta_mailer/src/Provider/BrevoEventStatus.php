<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Provider;

use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Estado de entrega de uma mensagem transacional no Brevo, pela API de estatísticas (somente leitura).
 *
 * Consulta `GET /v3/smtp/statistics/events` com `messageId` entre colchetes angulares e `days`. Sem os colchetes, a API
 * devolve lista vazia mesmo quando há eventos (verificado em 2026-10-20). A chave vem do arquivo de segredos
 * (BREVO_API_KEY) e nunca é registrada.
 */
final class BrevoEventStatus {

  /**
   * Endpoint de eventos de envios transacionais.
   */
  public const ENDPOINT = 'https://api.brevo.com/v3/smtp/statistics/events';

  public function __construct(
    private readonly ?SecretProviderInterface $secrets = NULL,
    private readonly ?HttpClientInterface $http = NULL,
  ) {}

  /**
   * Eventos da mensagem nos últimos `$days` dias: nome e data, em ordem da API.
   *
   * @param string $providerMessageId
   *   ID que o Brevo deu à mensagem (campo provider_message_id do registro), com ou sem colchetes.
   * @param int $days
   *   Janela de consulta, de 1 a 90 dias.
   *
   * @return array<int, array{event: string, date: string}>|null
   *   Eventos, ou NULL quando a consulta não pôde ser feita (sem chave, sem cliente HTTP ou falha da API).
   */
  public function events(string $providerMessageId, int $days = 7): ?array {
    $key = (string) ($this->secrets?->value('BREVO_API_KEY') ?? '');
    $id = trim($providerMessageId, '<> ');
    if ($key === '' || $id === '' || $this->http === NULL) {
      return NULL;
    }
    $days = max(1, min(90, $days));
    $query = http_build_query(['messageId' => '<' . $id . '>', 'days' => $days]);
    try {
      $response = $this->http->request('GET', self::ENDPOINT . '?' . $query, [
        'headers' => ['api-key' => $key, 'accept' => 'application/json'],
        'timeout' => 20,
      ]);
      if ($response->getStatusCode() !== 200) {
        return NULL;
      }
      $data = $response->toArray(FALSE);
    }
    catch (\Throwable) {
      return NULL;
    }
    // A resposta é um objeto com a chave `events` (verificado em 2026-10-20); lista direta também é aceita.
    $list = is_array($data) && isset($data['events']) && is_array($data['events']) ? $data['events'] : $data;
    if (!is_array($list)) {
      return NULL;
    }
    $events = [];
    foreach ($list as $item) {
      if (is_array($item)) {
        $events[] = ['event' => (string) ($item['event'] ?? ''), 'date' => (string) ($item['date'] ?? '')];
      }
    }
    return $events;
  }

}
