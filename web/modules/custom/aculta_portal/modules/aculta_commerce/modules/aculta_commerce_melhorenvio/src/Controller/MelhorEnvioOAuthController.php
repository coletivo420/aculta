<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Controller;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClient;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\aculta_commerce_melhorenvio\Http\OAuthState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fluxo OAuth2 do Melhor Envio: inicia a autorização e recebe o retorno com validação do `state`.
 *
 * Respostas para o navegador usam as mensagens do Drupal (alertas exibidos pelo tema) e redirecionam para a página
 * inicial. Nenhuma resposta de texto puro vai ao usuário.
 */
final class MelhorEnvioOAuthController {

  use StringTranslationTrait;

  /**
   * Chave da sessão que guarda o `state` emitido.
   */
  private const SESSION_KEY = 'aculta_commerce_melhorenvio.oauth_state';

  /**
   * Canal de log do módulo.
   */
  private readonly LoggerInterface $logger;

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly MelhorEnvioClient $client,
    private readonly OAuthState $state,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly MessengerInterface $messenger,
  ) {
    $this->logger = $loggerFactory->get('aculta_commerce_melhorenvio');
  }

  /**
   * Gera um `state`, guarda na sessão e redireciona para a tela de autorização do Melhor Envio.
   */
  public function authorize(Request $request): Response {
    $state = $this->state->issue();
    $request->getSession()->set(self::SESSION_KEY, $state);
    // Só o hash do state vai para o log, para comparar com o retorno sem expor o valor.
    $this->logger->notice('Autorização do Melhor Envio iniciada: state {hash}.', ['hash' => substr(hash('sha256', $state), 0, 12)]);
    try {
      // Destino externo: o Drupal recusa RedirectResponse comum para host externo.
      $response = new TrustedRedirectResponse($this->client->authorizationUrl($state));
      // TrustedRedirectResponse é cacheável por padrão; o state é de uso único e não pode ser reaproveitado.
      $response->getCacheableMetadata()->setCacheMaxAge(0);
      $response->headers->set('Cache-Control', 'private, no-store');
      return $response;
    }
    catch (MelhorEnvioException) {
      $this->messenger->addError($this->t('A integração com o Melhor Envio não está configurada para este ambiente.'));
      return $this->redirectToFront();
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
    $received = (string) $request->query->get('state', '');
    $this->logger->notice('Retorno do Melhor Envio: state {received}, pendente na sessão: {pending}.', [
      'received' => $received === '' ? '(vazio)' : substr(hash('sha256', $received), 0, 12),
      'pending' => is_string($expected) && $expected !== '' ? substr(hash('sha256', $expected), 0, 12) : 'nenhum',
    ]);
    if (!$this->state->matches(is_string($expected) ? $expected : NULL, $received)) {
      $this->messenger->addError($this->t('A autorização do Melhor Envio não pôde ser confirmada. Inicie a conexão de novo a partir do site.'));
      return $this->redirectToFront();
    }
    $code = (string) $request->query->get('code', '');
    if ($code === '') {
      $this->messenger->addError($this->t('O Melhor Envio não devolveu o código de autorização. Tente de novo.'));
      return $this->redirectToFront();
    }
    try {
      $this->client->exchangeAuthorizationCode($code);
    }
    catch (MelhorEnvioException) {
      $this->messenger->addError($this->t('Não foi possível concluir a autorização com o Melhor Envio. Aguarde um instante e tente novamente.'));
      return $this->redirectToFront();
    }
    $this->messenger->addStatus($this->t('Conexão com o Melhor Envio autorizada.'));
    return $this->redirectToFront();
  }

  /**
   * Redireciona para a página inicial, onde o tema exibe as mensagens do Drupal.
   */
  private function redirectToFront(): RedirectResponse {
    $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}
