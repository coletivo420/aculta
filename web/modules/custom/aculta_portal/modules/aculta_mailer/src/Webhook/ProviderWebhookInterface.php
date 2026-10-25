<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Webhook;

use Symfony\Component\HttpFoundation\Request;

/**
 * Webhook de um provedor de e-mail (serviço com tag `aculta_mailer.webhook_provider`).
 *
 * `verify()` valida a assinatura do provedor antes de qualquer evento ser aceito. Sem assinatura válida, nada é lido
 * nem gravado. A implementação concreta depende do provedor escolhido pelo responsável.
 */
interface ProviderWebhookInterface {

  /**
   * Identificador do provedor, usado na rota (ex.: `teste`).
   */
  public function id(): string;

  /**
   * Indica se a requisição está assinada pelo provedor.
   */
  public function verify(Request $request): bool;

  /**
   * Eventos da requisição, já normalizados.
   *
   * @return \Drupal\aculta_mailer\Webhook\ProviderEvent[]
   *   Eventos validados.
   *
   * @throws \InvalidArgumentException
   *   Quando o corpo não tem o formato esperado.
   */
  public function parse(Request $request): array;

}
