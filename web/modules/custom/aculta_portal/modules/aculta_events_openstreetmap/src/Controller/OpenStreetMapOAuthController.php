<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Controller;

use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthClient;
use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthException;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapOAuthState;
use Drupal\aculta_portal\Integration\PopupReturnPage;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts and completes OpenStreetMap OAuth2 in the integration popup.
 */
final class OpenStreetMapOAuthController {

  use StringTranslationTrait;

  private const SESSION_KEY = 'aculta_events_openstreetmap.oauth_state';

  /**
   * Cria o fluxo OAuth com state de sessão e mensagens do Drupal.
   */
  public function __construct(
    private readonly OpenStreetMapOAuthClient $client,
    private readonly OpenStreetMapOAuthState $state,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Inicia autorização OAuth no popup e desativa cache da resposta. */
  public function authorize(Request $request): Response {
    $state = $this->state->issue();
    $request->getSession()->set(self::SESSION_KEY, $state);
    $redirect = Url::fromRoute('aculta_events_openstreetmap.oauth_callback', [], ['absolute' => TRUE])->toString();
    try {
      $response = new TrustedRedirectResponse($this->client->authorizationUrl($state, $redirect));
      $response->getCacheableMetadata()->setCacheMaxAge(0);
      $response->headers->set('Cache-Control', 'private, no-store');
      return $response;
    }
    catch (OpenStreetMapOAuthException) {
      $request->getSession()->remove(self::SESSION_KEY);
      $this->messenger->addError($this->t('A conexão com o OpenStreetMap não está configurada para este ambiente.'));
      return $this->returnPage($request);
    }
  }

  /**
   * Confere state de uso único e processa o retorno OAuth. */
  public function callback(Request $request): Response {
    $session = $request->getSession();
    $expected = $session->get(self::SESSION_KEY);
    $session->remove(self::SESSION_KEY);
    $received = (string) $request->query->get('state', '');
    if (!$this->state->matches(is_string($expected) ? $expected : NULL, $received)) {
      $this->messenger->addError($this->t('A autorização do OpenStreetMap não pôde ser confirmada. Inicie a conexão pelo ACULTA.'));
      return $this->returnPage($request);
    }
    if ($request->query->has('error')) {
      $this->messenger->addError($this->t('A autorização do OpenStreetMap foi cancelada ou recusada.'));
      return $this->returnPage($request);
    }
    $code = (string) $request->query->get('code', '');
    $redirect = Url::fromRoute('aculta_events_openstreetmap.oauth_callback', [], ['absolute' => TRUE])->toString();
    try {
      $this->client->exchangeAuthorizationCode($code, $redirect);
    }
    catch (OpenStreetMapOAuthException) {
      $this->messenger->addError($this->t('Não foi possível concluir a autorização do OpenStreetMap. Tente novamente.'));
      return $this->returnPage($request);
    }
    $this->messenger->addStatus($this->t('Conexão com o OpenStreetMap autorizada para este ambiente.'));
    return $this->returnPage($request);
  }

  /**
   * Fecha o popup e retorna à página de estado da integração. */
  private function returnPage(Request $request): Response {
    return PopupReturnPage::response($request, Url::fromRoute('aculta_events_openstreetmap.status')->toString());
  }

}
