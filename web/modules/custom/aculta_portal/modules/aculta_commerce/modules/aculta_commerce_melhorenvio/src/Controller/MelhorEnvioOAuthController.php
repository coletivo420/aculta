<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Controller;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClient;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\aculta_commerce_melhorenvio\Http\OAuthState;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fluxo OAuth2 do Melhor Envio: inicia a autorização e recebe o retorno com validação do `state`.
 */
final class MelhorEnvioOAuthController {

  /**
   * Chave da sessão que guarda o `state` emitido.
   */
  private const SESSION_KEY = 'aculta_commerce_melhorenvio.oauth_state';

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly MelhorEnvioClient $client,
    private readonly OAuthState $state,
  ) {}

  /**
   * Gera um `state`, guarda na sessão e redireciona para a tela de autorização do Melhor Envio.
   */
  public function authorize(Request $request): Response {
    $state = $this->state->issue();
    $request->getSession()->set(self::SESSION_KEY, $state);
    try {
      // Destino externo: o Drupal recusa RedirectResponse comum para host externo.
      return new TrustedRedirectResponse($this->client->authorizationUrl($state));
    }
    catch (MelhorEnvioException) {
      return new Response(
        'A integração com o Melhor Envio não está configurada para este ambiente.',
        Response::HTTP_SERVICE_UNAVAILABLE,
        ['Content-Type' => 'text/plain; charset=UTF-8'],
      );
    }
  }

  /**
   * Recebe o retorno do Melhor Envio. Só troca o código se o `state` for o emitido para esta sessão.
   */
  public function callback(Request $request): Response {
    $session = $request->getSession();
    $expected = $session->get(self::SESSION_KEY);
    // O state é de uso único: sai da sessão antes de qualquer outra verificação.
    $session->remove(self::SESSION_KEY);
    if (!$this->state->matches(is_string($expected) ? $expected : NULL, (string) $request->query->get('state', ''))) {
      throw new AccessDeniedHttpException('Retorno de autorização inválido.');
    }
    $code = (string) $request->query->get('code', '');
    if ($code === '') {
      throw new AccessDeniedHttpException('Código de autorização ausente.');
    }
    try {
      $this->client->exchangeAuthorizationCode($code);
    }
    catch (MelhorEnvioException) {
      return new Response(
        'Não foi possível concluir a autorização com o Melhor Envio. Tente novamente.',
        Response::HTTP_BAD_GATEWAY,
        ['Content-Type' => 'text/plain; charset=UTF-8'],
      );
    }
    return new Response(
      'Conexão com o Melhor Envio autorizada.',
      Response::HTTP_OK,
      ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store'],
    );
  }

}
