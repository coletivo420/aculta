<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Controller;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioConfig;
use Drupal\aculta_commerce_melhorenvio\Webhook\MelhorEnvioWebhookEvent;
use Drupal\aculta_commerce_melhorenvio\Webhook\MelhorEnvioWebhookSignature;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recebe as notificações de etiqueta do Melhor Envio.
 *
 * A rota é `/integracoes/melhorenvio/webhook`. Sem segredo configurado,
 * responde 503; assinatura inválida, 403; corpo fora do formato, 400; evento
 * válido, 204. Só o evento, o id da etiqueta e o status vão para o log.
 * O corpo não é gravado.
 */
final class MelhorEnvioWebhookController {

  /**
   * Teto do corpo para um webhook do Melhor Envio.
   *
   * 32 KiB deixa margem para campos futuros sem aceitar payloads arbitrários.
   */
  public const MAX_BODY_BYTES = 32768;

  /**
   * Canal de log do módulo.
   */
  private readonly LoggerInterface $logger;

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly MelhorEnvioConfig $config,
    private readonly KeyRepositoryInterface $keys,
    private readonly MelhorEnvioWebhookSignature $signature,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly ?SecretsManager $secrets = NULL,
  ) {
    $this->logger = $loggerFactory->get('aculta_commerce_melhorenvio');
  }

  /**
   * Recebe e valida uma notificação de etiqueta.
   */
  public function receive(Request $request): Response {
    $body = self::readBodyWithinLimit($request);
    if ($body === NULL) {
      return new Response(
        'Corpo do webhook acima do limite.',
        Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
        ['Content-Type' => 'text/plain; charset=UTF-8'],
      );
    }
    $secret = $this->clientSecret();
    if ($secret === NULL) {
      return new Response('Webhook do Melhor Envio não configurado neste ambiente.', Response::HTTP_SERVICE_UNAVAILABLE, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    if (!$this->signature->isValid($body, (string) $request->headers->get('X-ME-Signature', ''), $secret)) {
      return new Response('Assinatura inválida.', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    try {
      $event = MelhorEnvioWebhookEvent::fromJson($body);
    }
    catch (\InvalidArgumentException) {
      return new Response('Corpo do webhook inválido.', Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    $this->logger->notice('Webhook do Melhor Envio: {event}, etiqueta {id}, status {status}.', [
      'event' => $event->event,
      'id' => $event->labelId,
      'status' => $event->status,
    ]);
    return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
  }

  /**
   * Reads at most one byte beyond the body limit, without trusting the header.
   */
  private static function readBodyWithinLimit(Request $request): ?string {
    $declared = $request->headers->get('Content-Length');
    if ($declared !== NULL && ($declared === '' || !ctype_digit($declared)
      || (int) $declared > self::MAX_BODY_BYTES)) {
      return NULL;
    }
    $stream = $request->getContent(TRUE);
    if (!is_resource($stream)) {
      return NULL;
    }
    $body = stream_get_contents($stream, self::MAX_BODY_BYTES + 1);
    if ($body === FALSE || strlen($body) > self::MAX_BODY_BYTES) {
      return NULL;
    }
    return $body;
  }

  /**
   * Segredo do aplicativo do ambiente ativo, lido do Drupal Key.
   *
   * NULL quando não configurado.
   */
  private function clientSecret(): ?string {
    $id = $this->config->keyIds($this->config->environment())['client_secret'];
    $key = $id !== '' ? $this->keys->getKey($id) : NULL;
    $value = $key?->getKeyValue() ?? '';
    // Sem Key, o segredo vem do arquivo de credenciais do servidor, pelo nome
    // do contrato.
    if ($value === '' && $this->secrets !== NULL && $id !== '') {
      $value = (string) $this->secrets->value($id);
    }
    return $value === '' ? NULL : $value;
  }

}
