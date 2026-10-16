<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Commerce\MercadoPago;

use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionNotificationService;
use Drupal\Core\Logger\LoggerChannelInterface;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Validates modern Mercado Pago webhook requests before Commerce receives them. */
final class WebhookGuard {

  public function __construct(
    private readonly ?MercadoPagoSubscriptionNotificationService $assinaturas = NULL,
    private readonly ?LoggerChannelInterface $log = NULL,
  ) {}

  /**
   * Validates and adapts a notification, or returns a fail-closed response.
   *
   * A NULL result means that a signed, supported payment notification has been
   * normalized in memory and may continue to Commerce's contrib controller.
   */
  public function validateAndNormalize(Request $request, bool $gateway_enabled, ?string $secret): ?Response {
    if (!$gateway_enabled) {
      return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
    }
    if ($request->getMethod() !== 'POST') {
      return new Response('', Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
    }
    if ($secret === NULL || trim($secret) === '') {
      return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
    }

    $signature = $request->headers->get('x-signature');
    $request_id = $request->headers->get('x-request-id');
    if ($signature === NULL || trim($signature) === '' || $request_id === NULL || trim($request_id) === '') {
      return new Response('', Response::HTTP_UNAUTHORIZED);
    }

    $query = $request->query->all();
    // Unsigned legacy IPN uses topic/id. It is never forwarded, even if a
    // caller also supplies a modern-looking payload.
    if (array_key_exists('topic', $query)) {
      return new Response('', Response::HTTP_BAD_REQUEST);
    }

    $json = [];
    $body = $request->getContent();
    if ($body !== '' && str_contains(strtolower((string) $request->headers->get('content-type')), 'json')) {
      try {
        $decoded = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException) {
        return new Response('', Response::HTTP_BAD_REQUEST);
      }
      if (!is_array($decoded)) {
        return new Response('', Response::HTTP_BAD_REQUEST);
      }
      $json = $decoded;
    }

    $query_sources = [$query];
    $raw_query = (string) $request->server->get('QUERY_STRING', '');
    if ($raw_query !== '') {
      $parsed_query = [];
      parse_str($raw_query, $parsed_query);
      $query_sources[] = $parsed_query;
    }
    $data_ids = array_merge(
      $this->extractDataIds($query_sources),
      $this->extractDataIds([$request->request->all(), $json]),
    );
    $data_ids = array_values(array_unique($data_ids));
    if (count($data_ids) !== 1) {
      return new Response('', Response::HTTP_BAD_REQUEST);
    }
    $data_id = $data_ids[0];

    $types = $this->extractTypes([$query, $request->request->all(), $json]);
    $types = array_values(array_unique($types));
    if (count($types) !== 1) {
      return new Response('', Response::HTTP_BAD_REQUEST);
    }
    $tipo = $types[0];
    $assinatura = in_array($tipo, MercadoPagoSubscriptionNotificationService::TIPOS, TRUE);

    // Pagamentos usam ID inteiro (contrib legado). Assinaturas usam IDs alfanuméricos do Mercado Pago.
    if ($assinatura) {
      if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $data_id) !== 1) {
        return new Response('', Response::HTTP_BAD_REQUEST);
      }
    }
    // The installed contrib casts its legacy `id` query parameter to an
    // integer before calling the SDK. Restrict input to a positive PHP integer
    // so validation and the contrib lookup cannot refer to different IDs.
    elseif (filter_var($data_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
      return new Response('', Response::HTTP_BAD_REQUEST);
    }
    if (isset($query['id']) && (string) $query['id'] !== $data_id) {
      return new Response('', Response::HTTP_BAD_REQUEST);
    }

    try {
      // This SDK validator is stateless and performs no HTTP requests. It
      // normalizes data.id as required by Mercado Pago's official algorithm.
      WebhookSignatureValidator::validate($signature, $request_id, $data_id, $secret);
    }
    catch (InvalidWebhookSignatureException | \InvalidArgumentException) {
      return new Response('', Response::HTTP_UNAUTHORIZED);
    }

    if ($assinatura) {
      return $this->processarAssinatura($tipo, $data_id);
    }

    if ($tipo !== 'payment') {
      // A signed but unsupported notification is acknowledged without passing
      // it to the older contrib handler, avoiding retries for unhandled types.
      return new Response('', Response::HTTP_OK);
    }

    // Replace all incoming query values with the exact ID covered by the
    // validated signature and the legacy keys expected by this contrib code.
    $request->query->replace(['topic' => 'payment', 'id' => $data_id]);
    return NULL;
  }

  /** Extracts all supported data.id representations for conflict detection. */
  /**
   * Processa um tópico de assinatura já validado. 200 quando consumido; 500 para o Mercado Pago reenviar.
   */
  private function processarAssinatura(string $tipo, string $id): Response {
    if ($this->assinaturas === NULL) {
      return new Response('', Response::HTTP_OK);
    }
    try {
      $this->assinaturas->processar($tipo, $id);
    }
    catch (\Throwable $e) {
      $this->log?->error('Falha ao processar notificação @tipo @id: @erro', ['@tipo' => $tipo, '@id' => $id, '@erro' => $e->getMessage()]);
      return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    return new Response('', Response::HTTP_OK);
  }

  private function extractDataIds(array $sources): array {
    $ids = [];
    foreach ($sources as $source) {
      if (!is_array($source)) {
        continue;
      }
      foreach (['data.id', 'data_id'] as $key) {
        if (array_key_exists($key, $source)) {
          $value = $this->scalarString($source[$key]);
          if ($value === NULL) {
            return [''];
          }
          $ids[] = $value;
        }
      }
      if (isset($source['data'])) {
        if (!is_array($source['data']) || !array_key_exists('id', $source['data'])) {
          return [''];
        }
        $value = $this->scalarString($source['data']['id']);
        if ($value === NULL) {
          return [''];
        }
        $ids[] = $value;
      }
    }
    return $ids;
  }

  /** Extracts webhook event types from query, form, and JSON representations. */
  private function extractTypes(array $sources): array {
    $types = [];
    foreach ($sources as $source) {
      if (is_array($source) && array_key_exists('type', $source)) {
        $value = $this->scalarString($source['type']);
        if ($value === NULL || $value === '') {
          return [''];
        }
        $types[] = $value;
      }
    }
    return $types;
  }

  /** Returns a trimmed scalar string without coercing arrays or objects. */
  private function scalarString(mixed $value): ?string {
    if (!is_string($value) && !is_int($value)) {
      return NULL;
    }
    return trim((string) $value);
  }

}
