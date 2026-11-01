<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Guards only the native Commerce notification route for Mercado Pago. */
final class WebhookEventSubscriber implements EventSubscriberInterface {

  private const ROUTE = 'commerce_payment.notify';
  private const GATEWAY_ID = 'mercado_pago';
  private const KEY_ID = 'mercadopago_webhook_secret';
  private const ENVIRONMENT_VARIABLE = 'MERCADOPAGO_WEBHOOK_SECRET';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly WebhookGuard $guard,
  ) {}

  /**
   * {@inheritdoc}
   *
   * RouterListener runs at priority 32. Priority 29 runs after routing and
   * route normalization, before controller dispatch and lower-priority cache
   * subscribers can short-circuit this non-cacheable POST endpoint.
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onKernelRequest', 29]];
  }

  /**
   * Validates and adapts the request before Commerce calls the gateway. */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== self::ROUTE) {
      return;
    }

    $route_gateway = $request->attributes->get('commerce_payment_gateway');
    $gateway_id = $route_gateway instanceof PaymentGatewayInterface
      ? $route_gateway->id()
      : (is_string($route_gateway) ? $route_gateway : '');
    if ($gateway_id !== self::GATEWAY_ID) {
      return;
    }

    $gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load(self::GATEWAY_ID);
    if (!$gateway instanceof PaymentGatewayInterface || !$gateway->status()) {
      $event->setResponse($this->guard->validateAndNormalize($request, FALSE, NULL));
      return;
    }

    $secret = $this->getEnvironmentSecret();
    $response = $this->guard->validateAndNormalize($request, TRUE, $secret);
    if ($response !== NULL) {
      $event->setResponse($response);
    }
  }

  /**
   * Reads the webhook signing secret only through its environment-backed Key. */
  private function getEnvironmentSecret(): ?string {
    $key = $this->keyRepository->getKey(self::KEY_ID);
    if (!$key || $key->getKeyProvider()->getPluginId() !== 'env') {
      return NULL;
    }
    $provider_settings = $key->getKeyProvider()->getConfiguration();
    if (($provider_settings['env_variable'] ?? NULL) !== self::ENVIRONMENT_VARIABLE) {
      return NULL;
    }
    $value = $key->getKeyValue(TRUE);
    return is_string($value) && trim($value) !== '' ? $value : NULL;
  }

}
