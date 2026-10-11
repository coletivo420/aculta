<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Enforces route purpose and canonicalizes admin navigation to MAIN. */
final class DomainPurposeRequestSubscriber implements EventSubscriberInterface {

  private const LOGIN_DESTINATION_SESSION_KEY = 'aculta_portal.login_destination';

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly RequestMatcherInterface $accessFreeMatcher,
    private readonly AccountProxyInterface $currentUser,
    private readonly RouteProviderInterface $routeProvider,
    private readonly \Drupal\aculta_portal\Domain\ContentPurposeResolver $contentPurposeResolver,
  ) {}

  public static function getSubscribedEvents(): array {
    // Match the same path before RouterListener/access checks (priority 32).
    return [
      KernelEvents::REQUEST => [['onRequestBeforeRouter', 33], ['onRequest', 31]],
      // Run before Core RedirectResponseSubscriber (priority 0) so any
      // intentional cross-domain target is already a secured redirect.
      KernelEvents::RESPONSE => ['onResponse', 1],
    ];
  }

  /** Sends completed logouts to the public account login page. */
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
        && isset($destination['purpose'], $destination['path'])
        && is_string($destination['purpose'])
        && is_string($destination['path'])
        && $this->isSafeLoginDestinationPath($destination['path'])) {
        $url = $this->domainPurposeManager->pathUrl($destination['purpose'], $destination['path']);
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
      $accountRoot = $this->domainPurposeManager->pathUrl('account', '/');
      if ($accountRoot !== NULL) {
        $this->retargetRedirect($event, $accountRoot->toString());
        return;
      }
    }

    if ($route !== 'user.logout' || $this->currentUser->isAuthenticated()) {
      return;
    }

    $login = $this->domainPurposeManager->routeUrl('account', 'user.login');
    if ($login) {
      // Keep Core's status, cookies, and headers after a successful logout.
      $this->retargetRedirect($event, $login->toString());
    }
  }

  /** Enforces wrong-host policy before the access-aware router can return 403. */
  public function onRequestBeforeRouter(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
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
      // wrong-purpose mutations fail closed without duplicating route families.
      $request = $event->getRequest();
      if ($this->domainPurposeManager->getCurrentPurpose() !== 'main'
        && !in_array($request->getMethod(), ['GET', 'HEAD'], TRUE)) {
        foreach ($this->routeProvider->getRouteCollectionForRequest($request) as $candidate) {
          if ($candidate->getOption('_admin_route')
            || $candidate->getOption('_aculta_domain_purpose') === 'main'
            || $candidate->getOption('_aculta_cross_domain_canonical_purpose') === 'main') {
            $event->setResponse($this->notFoundResponse());
            return;
          }
        }
      }
      return;
    }
    // The overview route requires a permission, so Core denies it (403) during
    // matching, before onRequest runs. Its internal path must fail closed as 404
    // first, the same way the support route is handled.
    if ($matched['_route'] === 'aculta_portal.dashboard' && $event->getRequest()->getPathInfo() !== '/') {
      $event->setResponse($this->notFoundResponse());
      return;
    }
    // Visitante não logado na raiz da Conta vai ao login do próprio host de conta (302: o estado de login muda).
    // Feito antes do roteamento com acesso, que negaria a rota (403) antes de chegar a onRequest.
    if ($matched['_route'] === 'aculta_portal.dashboard' && !$this->currentUser->isAuthenticated()) {
      $login = $this->domainPurposeManager->routeUrl('account', 'user.login');
      if ($login !== NULL) {
        $event->setResponse(new TrustedRedirectResponse($login->toString(), Response::HTTP_FOUND));
        return;
      }
    }
    $route = $this->routeProvider->getRouteByName($matched['_route']);
    $requiredPurpose = $this->contentPurposeResolver->requiredPurpose(
      $route,
      $matched['_route'],
      $event->getRequest(),
      $matched,
    );
    $currentPurpose = $this->domainPurposeManager->getCurrentPurpose();
    if (is_string($requiredPurpose) && $currentPurpose !== $requiredPurpose) {
      if ($this->isPasswordResetEditException(
        $event->getRequest(),
        (string) $matched['_route'],
        $currentPurpose,
        $matched,
      )) {
        return;
      }
      if ($requiredPurpose === 'main'
        && ($this->isAdministrativeRoute($route)
          || $route->getOption('_aculta_cross_domain_canonical_purpose') === 'main')) {
        $event->setResponse($this->canonicalMainNavigationResponse($event->getRequest()));
        return;
      }
      $event->setResponse($this->notFoundResponse());
    }
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $routeName = (string) $request->attributes->get('_route');
    // The support form is routed at /apoio but is the public home of the SUPPORT
    // subdomain: it is served only when the original request path is /.
    if ($routeName === 'aculta_portal.support_form' && $request->getPathInfo() !== '/' && !$request->attributes->get(\Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor::FRONT_ATTRIBUTE)) {
      throw new NotFoundHttpException();
    }
    // The account overview is served only at the ACCOUNT root. Its internal
    // route path is never a public URL, so direct requests to it fail closed.
    if ($routeName === 'aculta_portal.dashboard' && $request->getPathInfo() !== '/') {
      throw new NotFoundHttpException();
    }
    if (in_array($routeName, ['user.login', 'social_auth.network.redirect'], TRUE)
      && !$this->currentUser->isAuthenticated()
      && $request->hasSession()) {
      $session = $request->getSession();
      $destination = $this->parseLoginDestination($request->query->get('destination'));
      $requestedPurpose = $request->query->get('aculta_destination_purpose');

      // A direct/fresh login must not inherit a destination abandoned earlier
      // in the same anonymous session.
      if ($routeName === 'user.login' && $destination === NULL) {
        $session->remove(self::LOGIN_DESTINATION_SESSION_KEY);
      }

      if ($destination !== NULL) {
        $storedDestination = $session->get(self::LOGIN_DESTINATION_SESSION_KEY);
        $destinationPurpose = is_string($requestedPurpose)
          && $this->domainPurposeManager->getDomain($requestedPurpose)
            ? $requestedPurpose
            : NULL;

        // Social Auth forwards Drupal's standard destination but not the
        // ACULTA-specific purpose. Preserve the purpose captured on /entrar
        // when the OAuth request refers to the same path and query.
        if ($destinationPurpose === NULL
          && $routeName === 'social_auth.network.redirect'
          && is_array($storedDestination)
          && ($storedDestination['path'] ?? NULL) === $destination['path']
          && ($storedDestination['query'] ?? []) === $destination['query']
          && is_string($storedDestination['purpose'] ?? NULL)
          && $this->domainPurposeManager->getDomain($storedDestination['purpose']) !== NULL) {
          $destinationPurpose = $storedDestination['purpose'];
        }

        $destinationPurpose ??= $this->domainPurposeManager->getCurrentPurpose();
        if ($destinationPurpose !== NULL) {
          $session->set(self::LOGIN_DESTINATION_SESSION_KEY, [
            'purpose' => $destinationPurpose,
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
    $requiredPurpose = $this->contentPurposeResolver->requiredPurpose(
      $route,
      (string) $request->attributes->get('_route'),
      $request,
    );
    $currentPurpose = $this->domainPurposeManager->getCurrentPurpose();

    $resetEditException = $this->isPasswordResetEditException(
      $request,
      (string) $request->attributes->get('_route'),
      $currentPurpose,
    );
    if (is_string($requiredPurpose) && $currentPurpose !== $requiredPurpose && !$resetEditException) {
      if ($requiredPurpose === 'main'
        && ($this->isAdministrativeRoute($route)
          || $route->getOption('_aculta_cross_domain_canonical_purpose') === 'main')) {
        $event->setResponse($this->canonicalMainNavigationResponse($request));
        return;
      }
      $this->notFound($event);
      return;
    }

    $contentPurpose = $this->contentPurposeResolver->canonicalContentPurpose($request);
    if ($contentPurpose !== NULL && $currentPurpose !== $contentPurpose) {
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
      || str_starts_with($path, '/painel-administrativo/');
  }

  /**
   * Canonicalizes safe MAIN-owned navigation and fails closed otherwise.
   */
  private function canonicalMainNavigationResponse(Request $request): Response {
    // Never replay a state-changing request across Domain boundaries.
    if (!in_array($request->getMethod(), ['GET', 'HEAD'], TRUE)) {
      return $this->notFoundResponse();
    }

    $url = $this->domainPurposeManager->pathUrl('main', $request->getPathInfo());
    if ($url === NULL) {
      return $this->notFoundResponse();
    }

    $query = $request->query->all();
    if ($query !== []) {
      $url->setOption('query', $query);
    }

    return new TrustedRedirectResponse($url->toString(), Response::HTTP_FOUND, [
      'Cache-Control' => 'private, no-store',
      'X-Robots-Tag' => 'noindex, nofollow',
    ]);
  }

  /**
   * Replaces a redirect target with an explicitly trusted Domain-managed URL.
   */
  private function retargetRedirect(ResponseEvent $event, string $target): void {
    $response = $event->getResponse();
    if ($response instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
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
   * @param array<string, mixed> $matched
   *   Access-free router parameters when called before RouterListener.
   */
  private function isPasswordResetEditException(
    Request $request,
    string $routeName,
    ?string $currentPurpose,
    array $matched = [],
  ): bool {
    if ($routeName !== 'entity.user.edit_form' || $currentPurpose !== 'account') {
      return FALSE;
    }

    $raw = $request->attributes->get('_raw_variables');
    $uid = $request->attributes->get('user')
      ?? ($matched['user'] ?? NULL)
      ?? ($raw instanceof \Symfony\Component\HttpFoundation\ParameterBag ? $raw->get('user') : NULL);

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

  /** Checks an already-parsed internal path for post-login reuse. */
  private function isSafeLoginDestinationPath(string $path): bool {
    return str_starts_with($path, '/')
      && !str_starts_with($path, '//')
      && !preg_match('#^/(?:entrar|oauth|sair|recuperar(?:-senha|-acesso)?)(?:/|$)#', $path);
  }

  private function notFound(RequestEvent $event): void {
    $event->setResponse($this->notFoundResponse());
  }

  private function notFoundResponse(): Response {
    return new Response('', Response::HTTP_NOT_FOUND, [
      'Cache-Control' => 'private, no-store',
      'X-Robots-Tag' => 'noindex, nofollow',
    ]);
  }

}
