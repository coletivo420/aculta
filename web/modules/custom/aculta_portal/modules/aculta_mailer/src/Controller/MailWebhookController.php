<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Webhook\DeliveryEventApplier;
use Drupal\aculta_mailer\Webhook\ProviderWebhookRegistry;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Recebe eventos de entrega dos provedores. Endpoint técnico, sem sessão: a assinatura do provedor é a autenticação.
 */
final class MailWebhookController extends ControllerBase {

  /**
   * Teto do corpo de um pedido de evento. Um evento do Brevo tem poucas centenas de bytes; o teto impede corpos grandes.
   */
  public const MAX_BODY_BYTES = 16384;

  public function __construct(
    private readonly ProviderWebhookRegistry $providers,
    private readonly DeliveryEventApplier $applier,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ProviderWebhookRegistry::class),
      $container->get(DeliveryEventApplier::class),
    );
  }

  /**
   * Indica se o pedido cabe no teto: confere o Content-Length declarado e o corpo efetivo.
   */
  public static function bodyWithinLimit(Request $request): bool {
    $declared = $request->headers->get('Content-Length');
    if ($declared !== NULL && $declared !== '' && (!ctype_digit($declared) || (int) $declared > self::MAX_BODY_BYTES)) {
      return FALSE;
    }
    return strlen((string) $request->getContent()) <= self::MAX_BODY_BYTES;
  }

  /**
   * Valida a assinatura, lê os eventos e os aplica. Responde com o número de registros alterados.
   */
  public function receive(string $provider, Request $request): Response {
    if (!$this->providers->has($provider)) {
      throw new NotFoundHttpException();
    }
    // O tamanho é conferido antes da assinatura: um corpo grande não é lido nem por quem não está autenticado.
    if (!self::bodyWithinLimit($request)) {
      throw new HttpException(413, 'Corpo do pedido acima do limite de eventos.');
    }
    $webhook = $this->providers->get($provider);
    if (!$webhook->verify($request)) {
      // Sem assinatura válida, nada é lido nem gravado.
      throw new AccessDeniedHttpException();
    }
    try {
      $events = $webhook->parse($request);
    }
    catch (\InvalidArgumentException) {
      throw new BadRequestHttpException();
    }
    $applied = 0;
    foreach ($events as $event) {
      if ($this->applier->apply($event)) {
        $applied++;
      }
    }
    return new JsonResponse(['applied' => $applied]);
  }

}
