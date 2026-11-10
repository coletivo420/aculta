<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\aculta_portal\Checkout\CheckoutSlugMap;
use Drupal\aculta_portal\Domain\ContentSiteKeyResolver;
use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\FrontPathPolicy;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Enforces route site-key policy and canonicalizes admin navigation to MAIN.
 */
final class DomainSiteRequestSubscriber implements EventSubscriberInterface {

  private const LOGIN_DESTINATION_SESSION_KEY = 'aculta_portal.login_destination';

  public function __construct(
    private readonly DomainSiteManagerInterface $domainSiteManager,
    private readonly RequestMatcherInterface $accessFreeMatcher,
    private readonly AccountProxyInterface $currentUser,
    private readonly RouteProviderInterface $routeProvider,
    private readonly ContentSiteKeyResolver $contentSiteKeyResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Match the same path before RouterListener/access checks (priority 32).
    return [
      // Set root flags before Redirect's route normalizer (priority 30). The
      // inbound path processor can have a cached path result without its
      // request attributes, so the flags must also be restored here.
      KernelEvents::REQUEST => [
        ['onRequestDisableFrontNormalizer', 35],
        ['onRequestBeforeRouter', 33],
        ['onRequest', 31],
      ],
      // Run before Core RedirectResponseSubscriber (priority 0) so any
      // intentional cross-domain target is already a secured redirect.
      KernelEvents::RESPONSE => ['onResponse', 1],
    ];
  }

  /**
   * Prevents Redirect from canonicalizing a site root to its internal path.
   */
  public function onRequestDisableFrontNormalizer(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    PurposeFrontPathProcessor::markRootRequest(
      $event->getRequest(),
      $this->domainSiteManager->getCurrentSiteKey()?->value,
    );
  }

  /**
   * Sends completed logouts to the public account login page. */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest() || !$event->getResponse()->isRedirection()) {
      return;
    }

    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    if (in_array($route, ['user.login', 'social_auth.network.callback'], TRUE)
      && $this->currentUser->isAuthenticated()
      && $request->hasSession()) {
      $destination = $request->getSession()->get(self::LOGIN_DESTINATION_SESSION_KEY);
      $request->getSession()->remove(self::LOGIN_DESTINATION_SESSION_KEY);
      if (is_array($destination)
        && isset($destination['site_key'], $destination['path'])
        && is_string($destination['site_key'])
        && is_string($destination['path'])
        && $this->isSafeLoginDestinationPath($destination['path'])) {
        $siteKey = SiteKey::tryFrom($destination['site_key']);
        $url = $siteKey === NULL ? NULL : $this->domainSiteManager->pathUrl($siteKey, $destination['path']);
        if ($url !== NULL) {
          $query = $destination['query'] ?? [];
          if (is_array($query) && $query !== []) {
            $url->setOption('query', $query);
          }
          $this->retargetRedirect($event, $url->toString());
          return;
        }
      }

      // ACCOUNT's Domain root is the public landing URL. Its domain-specific
      // front page resolves the overview route internally.
      $accountRoot = $this->domainSiteManager->pathUrl(SiteKey::Account, '/');
      if ($accountRoot !== NULL) {
        $this->retargetRedirect($event, $accountRoot->toString());
        return;
      }
    }

    if ($route !== 'user.logout' || $this->currentUser->isAuthenticated()) {
      return;
    }

    $login = $this->domainSiteManager->routeUrl(SiteKey::Account, 'user.login');
    if ($login) {
      // Keep Core's status, cookies, and headers after a successful logout.
      $this->retargetRedirect($event, $login->toString());
    }
  }

  /**
   * Enforces wrong-host policy before the access-aware router can return 403.
   */
  public function onRequestBeforeRouter(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    // Drupal AJAX fragments are only delivered for AJAX requests.
    // Acesso direto no navegador responde 403.
    $ajaxQuery = $event->getRequest()->query;
    if (!$event->getRequest()->isXmlHttpRequest() && ($ajaxQuery->get('_wrapper_format') === 'drupal_ajax' || $ajaxQuery->has('ajax_form'))) {
      $event->setResponse(new Response('', Response::HTTP_FORBIDDEN, ['Cache-Control' => 'private, no-store']));
      return;
    }
    // O prefixo /studio saiu: WE SATIVA e GREEN STASH têm caminhos próprios
    // (/we-sativa e /green-stash). Qualquer /studio responde 404.
    $accountPath = $event->getRequest()->getPathInfo();
    if ($accountPath === '/studio' || str_starts_with($accountPath, '/studio/')) {
      $event->setResponse($this->notFoundResponse());
      return;
    }
    // /minha-conta remains a protected technical path, not a public alias.
    if ($accountPath === '/minha-conta' || str_starts_with($accountPath, '/minha-conta/')) {
      $event->setResponse(new Response('', Response::HTTP_NOT_FOUND, ['Cache-Control' => 'private, no-store']));
      return;
    }
    // Old checkout URLs redirect GET/HEAD to their public Portuguese path.
    // Other methods fail closed with 404.
    $oldCheckoutRequest = $event->getRequest();
    $publicCheckout = CheckoutSlugMap::toPublic($oldCheckoutRequest->getPathInfo());
    if ($publicCheckout !== NULL) {
      if (in_array($oldCheckoutRequest->getMethod(), ['GET', 'HEAD'], TRUE)) {
        $query = $oldCheckoutRequest->getQueryString();
        $event->setResponse(new LocalRedirectResponse($publicCheckout . ($query ? '?' . $query : ''), Response::HTTP_MOVED_PERMANENTLY));
      }
      else {
        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND, ['Cache-Control' => 'private, no-store']));
      }
      return;
    }
    try {
      $matched = $this->accessFreeMatcher->matchRequest($event->getRequest());
    }
    catch (ResourceNotFoundException) {
      return;
    }
    catch (MethodNotAllowedException) {
      // The path may still resolve to a route whose method requirements reject
      // this request. Inspect Drupal's path candidates and their metadata so
      // Wrong-site mutations fail closed without duplicating route families.
      $request = $event->getRequest();
      $mustRejectWrongHostMutation = $this->domainSiteManager->getCurrentSiteKey()?->value !== DomainSurfaceMap::MAIN
        && !in_array($request->getMethod(), ['GET', 'HEAD'], TRUE);
      foreach ($this->routeProvider->getRouteCollectionForRequest($request) as $candidate) {
        $invalidMetadata = DomainRoutePolicy::hasInvalidSiteKeyOptions($candidate);
        $wrongHostMainMutation = $mustRejectWrongHostMutation
          && ($candidate->getOption('_admin_route')
            || DomainRoutePolicy::declaredSiteKey($candidate) === DomainSurfaceMap::MAIN
            || DomainRoutePolicy::canonicalSiteKey($candidate) === DomainSurfaceMap::MAIN);
        if ($invalidMetadata || $wrongHostMainMutation) {
          $event->setResponse($this->notFoundResponse());
          return;
        }
      }
      return;
    }
    // Redirect anonymous visitors at the Account root to its login page.
    // This runs before access-aware routing would return 403.
    if ($matched['_route'] === 'aculta_portal.dashboard' && !$this->currentUser->isAuthenticated()) {
      $login = $this->domainSiteManager->routeUrl(SiteKey::Account, 'user.login');
      if ($login !== NULL) {
        $event->setResponse(new TrustedRedirectResponse($login->toString(), Response::HTTP_FOUND));
        return;
      }
    }
    // The Account root serves the private Studio dashboard; anonymous visitors
    // go to the account login page. The dashboard has no public standalone URL.
    $accountAction = FrontPathPolicy::acaoDaContaNaRaiz(
      $this->domainSiteManager->getCurrentSiteKey()?->value,
      $event->getRequest()->getPathInfo(),
      (string) $matched['_route'],
      $this->currentUser->isAuthenticated(),
    );
    if ($accountAction === FrontPathPolicy::ACAO_LOGIN) {
      $login = $this->domainSiteManager->routeUrl(SiteKey::Account, 'user.login');
      if ($login !== NULL) {
        $event->setResponse(new TrustedRedirectResponse($login->toString(), Response::HTTP_FOUND));
        return;
      }
    }
    $route = $this->routeProvider->getRouteByName($matched['_route']);
    if (DomainRoutePolicy::hasInvalidSiteKeyOptions($route)) {
      $event->setResponse($this->notFoundResponse());
      return;
    }
    $requiredSiteKey = $this->contentSiteKeyResolver->requiredSiteKey(
      $route,
      $matched['_route'],
      $event->getRequest(),
      $matched,
    );
    $currentSiteKey = $this->domainSiteManager->getCurrentSiteKey()?->value;
    // Test-only seam for single-host Functional installs. Production always
    // negotiates one Domain per site key, and this setting stays unset.
    if (Settings::get('aculta_site_key_test_relaxed', FALSE)) {
      return;
    }
    if (is_string($requiredSiteKey)
      && !DomainRoutePolicy::allowsSharedSite($route, $requiredSiteKey, (string) $currentSiteKey)) {
      if ($this->isPasswordResetEditException(
        $event->getRequest(),
        (string) $matched['_route'],
        $currentSiteKey,
        $matched,
      )) {
        return;
      }
      if ($requiredSiteKey === DomainSurfaceMap::MAIN
        && ($this->isAdministrativeRoute($route)
          || DomainRoutePolicy::canonicalSiteKey($route) === DomainSurfaceMap::MAIN)) {
        // Toda a interface administrativa responde somente no MAIN. Em
        // subdomínios, a rota administrativa é negada (404), sem redirect.
        if ($this->isAdministrativeRoute($route)) {
          $event->setResponse($this->notFoundResponse());
          return;
        }
        $event->setResponse($this->canonicalMainNavigationResponse($event->getRequest()));
        return;
      }
      $event->setResponse($this->notFoundResponse());
    }
  }

  /**
   * Enforces the site-key policy after the route has been matched.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $routeName = (string) $request->attributes->get('_route');
    // Internal paths for site roots are not public. Direct requests return
    // 404 before Redirect can normalize them.
    if (FrontPathPolicy::bloqueiaCaminhoInterno(
      $this->domainSiteManager->getCurrentSiteKey()?->value,
      $request->getPathInfo(),
      (bool) $request->attributes->get(PurposeFrontPathProcessor::FRONT_ATTRIBUTE),
    )) {
      throw new NotFoundHttpException();
    }
    if (in_array($routeName, ['user.login', 'social_auth.network.redirect'], TRUE)
      && !$this->currentUser->isAuthenticated()
      && $request->hasSession()) {
      $session = $request->getSession();
      $destination = $this->parseLoginDestination($request->query->get('destination'));
      $requestedSiteKey = $request->query->get('aculta_destination_site_key');

      // A direct/fresh login must not inherit a destination abandoned earlier
      // in the same anonymous session.
      if ($routeName === 'user.login' && $destination === NULL) {
        $session->remove(self::LOGIN_DESTINATION_SESSION_KEY);
      }

      if ($destination !== NULL) {
        $storedDestination = $session->get(self::LOGIN_DESTINATION_SESSION_KEY);
        $destinationSiteKey = is_string($requestedSiteKey) ? SiteKey::tryFrom($requestedSiteKey) : NULL;
        if ($destinationSiteKey !== NULL && $this->domainSiteManager->getDomain($destinationSiteKey) === NULL) {
          $destinationSiteKey = NULL;
        }

        // Social Auth forwards Drupal's standard destination but not the
        // ACULTA-specific site key. Preserve the site key captured on /entrar
        // when the OAuth request refers to the same path and query.
        if ($destinationSiteKey === NULL
          && $routeName === 'social_auth.network.redirect'
          && is_array($storedDestination)
          && ($storedDestination['path'] ?? NULL) === $destination['path']
          && ($storedDestination['query'] ?? []) === $destination['query']
          && is_string($storedDestination['site_key'] ?? NULL)
          && ($storedSiteKey = SiteKey::tryFrom($storedDestination['site_key'])) !== NULL
          && $this->domainSiteManager->getDomain($storedSiteKey) !== NULL) {
          $destinationSiteKey = $storedSiteKey;
        }

        $destinationSiteKey ??= $this->domainSiteManager->getCurrentSiteKey();
        if ($destinationSiteKey !== NULL) {
          $session->set(self::LOGIN_DESTINATION_SESSION_KEY, [
            'site_key' => $destinationSiteKey->value,
            'path' => $destination['path'],
            'query' => $destination['query'],
          ]);
        }
      }
    }

    $route = $request->attributes->get('_route_object');
    if (!$route || !method_exists($route, 'getOption')) {
      return;
    }
    if (DomainRoutePolicy::hasInvalidSiteKeyOptions($route)) {
      $this->notFound($event);
      return;
    }
    $requiredSiteKey = $this->contentSiteKeyResolver->requiredSiteKey(
      $route,
      (string) $request->attributes->get('_route'),
      $request,
    );
    $currentSiteKey = $this->domainSiteManager->getCurrentSiteKey()?->value;

    // Same test-only seam used before routing.
    if (Settings::get('aculta_site_key_test_relaxed', FALSE)) {
      return;
    }

    $resetEditException = $this->isPasswordResetEditException(
      $request,
      (string) $request->attributes->get('_route'),
      $currentSiteKey,
    );
    if (is_string($requiredSiteKey)
      && !DomainRoutePolicy::allowsSharedSite($route, $requiredSiteKey, (string) $currentSiteKey)
      && !$resetEditException) {
      if ($requiredSiteKey === DomainSurfaceMap::MAIN
        && ($this->isAdministrativeRoute($route)
          || DomainRoutePolicy::canonicalSiteKey($route) === DomainSurfaceMap::MAIN)) {
        // Toda a interface administrativa responde somente no MAIN. Em
        // subdomínios, a rota administrativa é negada (404), sem redirect.
        if ($this->isAdministrativeRoute($route)) {
          $event->setResponse($this->notFoundResponse());
          return;
        }
        $event->setResponse($this->canonicalMainNavigationResponse($request));
        return;
      }
      $this->notFound($event);
      return;
    }

    $contentSiteKey = $this->contentSiteKeyResolver->canonicalContentSiteKey($request);
    if ($contentSiteKey !== NULL
      && !DomainRoutePolicy::allowsSharedSite($route, $contentSiteKey, (string) $currentSiteKey)) {
      $this->notFound($event);
    }
  }

  /**
   * Returns TRUE when this route belongs to the centralized administration.
   */
  private function isAdministrativeRoute(object $route): bool {
    if (!method_exists($route, 'getOption') || !method_exists($route, 'getPath')) {
      return FALSE;
    }
    if ((bool) $route->getOption('_admin_route')) {
      return TRUE;
    }
    $path = (string) $route->getPath();
    return $path === '/painel-administrativo'
      || str_starts_with($path, '/painel-administrativo/')
      || $path === '/integracoes'
      || str_starts_with($path, '/integracoes/');
  }

  /**
   * Canonicalizes safe MAIN-owned navigation and fails closed otherwise.
   */
  private function canonicalMainNavigationResponse(Request $request): Response {
    // Never replay a state-changing request across Domain boundaries.
    if (!in_array($request->getMethod(), ['GET', 'HEAD'], TRUE)) {
      return $this->notFoundResponse();
    }

    $url = $this->domainSiteManager->pathUrl(SiteKey::Main, $request->getPathInfo());
    if ($url === NULL) {
      return $this->notFoundResponse();
    }

    $query = $request->query->all();
    if (isset($query['destination']) && is_string($query['destination'])) {
      // Core's RedirectResponseSubscriber resolves ?destination= on the
      // current host. Carry a safe return path to MAIN instead.
      $mainDestination = $this->mainDestinationUrl($query['destination']);
      $request->query->remove('destination');
      if ($mainDestination !== NULL) {
        $query['destination'] = $mainDestination;
      }
      else {
        unset($query['destination']);
      }
    }
    if ($query !== []) {
      $url->setOption('query', $query);
    }

    return new TrustedRedirectResponse($url->toString(), Response::HTTP_FOUND, [
      'Cache-Control' => 'private, no-store',
      'X-Robots-Tag' => 'noindex, nofollow',
    ]);
  }

  /**
   * Converts a safe relative destination into an absolute MAIN URL.
   */
  private function mainDestinationUrl(string $destination): ?string {
    if (!str_starts_with($destination, '/') || str_starts_with($destination, '//')) {
      return NULL;
    }
    $parts = parse_url($destination);
    if ($parts === FALSE || !isset($parts['path'])) {
      return NULL;
    }
    $url = $this->domainSiteManager->pathUrl(SiteKey::Main, $parts['path']);
    if ($url === NULL) {
      return NULL;
    }
    if (isset($parts['query'])) {
      parse_str($parts['query'], $query);
      $url->setOption('query', $query);
    }
    return $url->toString();
  }

  /**
   * Replaces a redirect target with an explicitly trusted Domain-managed URL.
   */
  private function retargetRedirect(ResponseEvent $event, string $target): void {
    $response = $event->getResponse();
    if ($response instanceof RedirectResponse) {
      $trusted = TrustedRedirectResponse::createFromRedirectResponse($response);
      $trusted->setTrustedTargetUrl($target);
      $event->setResponse($trusted);
      return;
    }

    $event->setResponse(new TrustedRedirectResponse(
      $target,
      $response->getStatusCode(),
      $response->headers->allPreserveCase(),
    ));
  }

  /**
   * Preserves Core's one-time password reset reuse of user edit on ACCOUNT.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   * @param string $routeName
   *   Matched route name.
   * @param string|null $currentSiteKey
   *   Site key of the current domain.
   * @param array<string, mixed> $matched
   *   Access-free router parameters when called before RouterListener.
   */
  private function isPasswordResetEditException(
    Request $request,
    string $routeName,
    ?string $currentSiteKey,
    array $matched = [],
  ): bool {
    if ($routeName !== 'entity.user.edit_form' || $currentSiteKey !== DomainSurfaceMap::ACCOUNT) {
      return FALSE;
    }

    $raw = $request->attributes->get('_raw_variables');
    $uid = $request->attributes->get('user')
      ?? ($matched['user'] ?? NULL)
      ?? ($raw instanceof ParameterBag ? $raw->get('user') : NULL);

    return is_numeric($uid)
      && AccountRouteSubscriber::isValidCorePasswordResetRequest(
        $request,
        (int) $uid,
        (int) $this->currentUser->id(),
      );
  }

  /**
   * Parses a Drupal-internal post-login destination into safe URL components.
   *
   * @return array{path: string, query: array}|null
   *   The normalized destination, or NULL for external/unsafe destinations.
   */
  private function parseLoginDestination(mixed $destination): ?array {
    if (!is_string($destination)
      || $destination === ''
      || !str_starts_with($destination, '/')
      || str_starts_with($destination, '//')) {
      return NULL;
    }

    $parts = parse_url($destination);
    if ($parts === FALSE
      || isset($parts['scheme'])
      || isset($parts['host'])
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['port'])
      || isset($parts['fragment'])) {
      return NULL;
    }

    $path = $parts['path'] ?? '';
    if (!$this->isSafeLoginDestinationPath($path)) {
      return NULL;
    }

    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
      parse_str($parts['query'], $query);
    }

    return ['path' => $path, 'query' => $query];
  }

  /**
   * Checks an already-parsed internal path for post-login reuse.
   */
  private function isSafeLoginDestinationPath(string $path): bool {
    return str_starts_with($path, '/')
      && !str_starts_with($path, '//')
      && !preg_match('#^/(?:entrar|oauth|sair|recuperar(?:-senha|-acesso)?)(?:/|$)#', $path);
  }

  /**
   * Returns a private 404 response for a request outside its site key.
   */
  private function notFound(RequestEvent $event): void {
    $event->setResponse($this->notFoundResponse());
  }

  /**
   * Creates a private 404 response that search engines must not index.
   */
  private function notFoundResponse(): Response {
    return new Response('', Response::HTTP_NOT_FOUND, [
      'Cache-Control' => 'private, no-store',
      'X-Robots-Tag' => 'noindex, nofollow',
    ]);
  }

}
