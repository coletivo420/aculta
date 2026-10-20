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

/**
 * Recebe eventos de entrega dos provedores. Endpoint técnico, sem sessão: a assinatura do provedor é a autenticação.
 */
final class MailWebhookController extends ControllerBase {

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
   * Valida a assinatura, lê os eventos e os aplica. Responde com o número de registros alterados.
   */
  public function receive(string $provider, Request $request): Response {
    if (!$this->providers->has($provider)) {
      throw new NotFoundHttpException();
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
