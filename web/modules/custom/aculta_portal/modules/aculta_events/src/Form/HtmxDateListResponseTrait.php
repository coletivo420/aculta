<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta HTMX que fecha o diálogo e atualiza a lista de Datas do Evento.
 *
 * A lista fica no formulário pai, fora do diálogo. Ela volta como fragmento
 * `hx-swap-oob`, então os campos ainda não salvos do Evento permanecem.
 */
trait HtmxDateListResponseTrait {

  /**
   * URL da rota do formulário no purpose ativo (main ou eventos).
   *
   * @param string $mainRoute
   *   Rota do host principal.
   * @param string $organizerRoute
   *   Rota do host de eventos.
   * @param array $parameters
   *   Parâmetros da rota.
   */
  protected function htmxRouteUrl(string $mainRoute, string $organizerRoute, array $parameters): Url {
    return Url::fromRoute($this->eventRouteContext->route($mainRoute, $organizerRoute), $parameters);
  }

  /**
   * Monta a resposta HTMX com a lista de Datas fora de banda.
   */
  public function htmxDateListResponse(Event $event): Response {
    $list = $this->dateListBuilder->build($event);
    // As mensagens de status viajam no próprio fragmento: a resposta HTMX não
    // renderiza a região global de mensagens.
    $list = [
      'messages' => ['#type' => 'status_messages', '#weight' => -100],
    ] + $list;
    $list['#attributes']['hx-swap-oob'] = 'outerHTML';
    return new Response(
      (string) $this->renderer->renderInIsolation($list),
      Response::HTTP_OK,
      [
        'Content-Type' => 'text/html; charset=UTF-8',
        'HX-Trigger' => 'aculta:close-dialog',
      ],
    );
  }

  /**
   * Redireciona pelo HTMX quando o evento não pode ser carregado.
   */
  protected function htmxRedirectResponse(string $route, array $parameters = []): Response {
    return new Response('', Response::HTTP_OK, [
      'HX-Redirect' => Url::fromRoute($route, $parameters)->toString(),
    ]);
  }

}
