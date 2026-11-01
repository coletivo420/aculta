<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;

/** Keeps ordinary users in the Portal for account management. */
final class AccountRouteSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  public static function getSubscribedEvents(): array {
    // RouterListener resolves _route at priority 32. This runs after routing,
    // before controller dispatch, and only examines explicit route names.
    return [KernelEvents::REQUEST => ['onKernelRequest', 29]];
  }

  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || !$this->currentUser->isAuthenticated()
      || $this->currentUser->hasPermission('administer users')) {
      return;
    }

    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    $target = $request->attributes->get('user');
    // RouterListener has matched the route, but entity parameter conversion
    // may not have run yet. The raw route value still identifies the target.
    if ($target === NULL) {
      $raw_parameters = $request->attributes->get('_raw_variables');
      $target = $raw_parameters instanceof ParameterBag ? $raw_parameters->get('user') : NULL;
    }
    $target_id = is_object($target) && method_exists($target, 'id') ? (int) $target->id() : (int) $target;
    if ($target_id <= 0 && $route === 'entity.user.edit_form'
      && preg_match('#^/user/([1-9][0-9]*)/edit$#D', $request->getPathInfo(), $matches)) {
      // The exact matched route gives us a final fallback before entity
      // conversion; never infer a target from arbitrary query parameters.
      $target_id = (int) $matches[1];
    }
    $own_uid = (int) $this->currentUser->id();
    $destination = NULL;

    if ($route === 'entity.user.edit_form') {
      // Drupal's validated one-time login flow lands here to set a password.
      // Only Core's session-bound token may bypass the Portal redirect.
      if (self::isValidCorePasswordResetRequest($request, $target_id, $own_uid)) {
        return;
      }
      if ($target_id === $own_uid && $own_uid > 0) {
        $destination = 'aculta_portal.security';
      }
      else {
        $event->setResponse(new Response('', 403));
        return;
      }
    }
    elseif ($route === 'entity.user.canonical') {
      if ($target_id === $own_uid) {
        // Canonical self-profile is an upstream compatibility route. The
        // public account landing page is the ACCOUNT Domain root.
        $destination = '<front>';
      }
      else {
        $event->setResponse(new \Symfony\Component\HttpFoundation\Response('', 403));
        return;
      }
    }

    if ($destination !== NULL) {
      $target = $destination === '<front>'
        ? $this->domainPurposeManager->pathUrl('account', '/')
        : $this->domainPurposeManager->routeUrl('account', $destination);
      if ($target === NULL) {
        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND));
        return;
      }

      // The generic user edit route is canonical on MAIN. Never turn a
      // state-changing request there into a cross-domain replay on ACCOUNT.
      if ($this->domainPurposeManager->getCurrentPurpose() !== 'account'
        && !in_array($request->getMethod(), ['GET', 'HEAD'], TRUE)) {
        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND, [
          'Cache-Control' => 'private, no-store',
          'X-Robots-Tag' => 'noindex, nofollow',
        ]));
        return;
      }

      $event->setResponse(new TrustedRedirectResponse($target->toString()));
    }
  }

  /**
   * Confirms the same session-bound token that Drupal Core's AccountForm uses.
   */
  public static function isValidCorePasswordResetRequest(Request $request, int $target_uid, int $current_uid): bool {
    if ($target_uid <= 0 || $target_uid !== $current_uid || !$request->hasSession()) {
      return FALSE;
    }
    $token = $request->query->get('pass-reset-token');
    $session_token = $request->getSession()->get('pass_reset_' . $target_uid);
    return is_string($token) && $token !== ''
      && is_string($session_token) && $session_token !== ''
      && hash_equals($session_token, $token);
  }

}
