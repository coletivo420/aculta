<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

/**
 * Gera um pedido novo de link de uso único no módulo dono do link (serviço com tag `aculta_mailer.link_regenerator`).
 *
 * O reenvio manual de um link de uso único nunca remonta o conteúdo antigo: o dono do link cria um token novo e uma
 * mensagem nova, e o envio da mensagem nova cancela o conteúdo anterior (ver docs/portal/LINKS-DE-USO-UNICO.md).
 */
interface SingleUseLinkRegenerator {

  /**
   * Indica se este dono gera o link do tipo de mensagem informado.
   *
   * @param string $type
   *   Tipo da mensagem (id do tipo do aculta_mailer).
   */
  public function supports(string $type): bool;

  /**
   * Cria o pedido novo para o destinatário. Retorna TRUE quando o transporte aceitou a mensagem nova.
   *
   * @param string $recipient
   *   Endereço do destinatário, como estava na mensagem original.
   */
  public function regenerate(string $recipient): bool;

}
