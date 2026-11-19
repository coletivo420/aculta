<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\ContentSiteKeyResolver;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\EventSubscriber\DomainSiteRequestSubscriber;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Route;

/**
 * Covers the direct login destination cutover to typed site keys.
 */
#[Group('aculta_portal')]
final class DomainSiteRequestSubscriberTest extends UnitTestCase {

  /**
   * Verifies that login stores the validated typed site key.
   */
  public function testLoginStoresTypedSiteKeyInSession(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->expects($this->once())
      ->method('getDomain')
      ->with(SiteKey::Links)
      ->willReturn($this->createMock(DomainInterface::class));
    $sites->method('getCurrentSiteKey')->willReturn(SiteKey::Main);

    $session = $this->createMock(SessionInterface::class);
    $session->expects($this->once())
      ->method('set')
      ->with('aculta_portal.login_destination', [
        'site_key' => 'links',
        'path' => '/checkout/42',
        'query' => ['step' => 'payment'],
      ]);
    $session->method('get')->with('aculta_portal.login_destination')->willReturn(NULL);

    $request = Request::create('/entrar', 'GET', [
      'destination' => '/checkout/42?step=payment',
      'aculta_destination_site_key' => 'links',
    ]);
    $request->attributes->set('_route', 'user.login');
    $request->attributes->set('_route_object', new Route('/entrar'));
    $request->setSession($session);

    $this->subscriber($sites)->onRequest($this->requestEvent($request));
  }

  /**
   * Verifies that purpose query/session data is not read after the cutover.
   */
  public function testPurposeQueryAndSessionFieldsAreNotReadAsSiteKey(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getCurrentSiteKey')->willReturn(SiteKey::Account);

    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->with('aculta_portal.login_destination')->willReturn([
      'purpose' => 'links',
      'path' => '/checkout/42',
      'query' => [],
    ]);
    $session->expects($this->once())
      ->method('set')
      ->with('aculta_portal.login_destination', [
        'site_key' => 'account',
        'path' => '/checkout/42',
        'query' => [],
      ]);

    $request = Request::create('/entrar', 'GET', [
      'destination' => '/checkout/42',
      'aculta_destination_purpose' => 'links',
    ]);
    $request->attributes->set('_route', 'user.login');
    $request->attributes->set('_route_object', new Route('/entrar'));
    $request->setSession($session);

    $this->subscriber($sites)->onRequest($this->requestEvent($request));
  }

  /**
   * Verifies the stored site key is used to build the post-login destination.
   */
  public function testPostLoginRedirectUsesStoredTypedSiteKey(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('https://links.example.test/checkout/42');
    $sites->expects($this->once())
      ->method('pathUrl')
      ->with(SiteKey::Links, '/checkout/42')
      ->willReturn($url);
    $session = $this->createMock(SessionInterface::class);
    $session->expects($this->once())->method('get')->with('aculta_portal.login_destination')->willReturn([
      'site_key' => 'links',
      'path' => '/checkout/42',
      'query' => [],
    ]);
    $session->expects($this->once())->method('remove')->with('aculta_portal.login_destination');
    $request = Request::create('/entrar');
    $request->attributes->set('_route', 'user.login');
    $request->setSession($session);
    $response = new RedirectResponse('/');
    $event = new ResponseEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );

    $this->subscriber($sites, TRUE)->onResponse($event);

    $this->assertSame('https://links.example.test/checkout/42', $event->getResponse()->headers->get('Location'));
  }

  /**
   * Builds the request subscriber with isolated policy collaborators.
   */
  private function subscriber(DomainSiteManagerInterface $sites, bool $authenticated = FALSE): DomainSiteRequestSubscriber {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $resolver = new ContentSiteKeyResolver($entities, $sites);
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('isAuthenticated')->willReturn($authenticated);

    return new DomainSiteRequestSubscriber(
      $sites,
      $this->createMock(RequestMatcherInterface::class),
      $user,
      $this->createMock(RouteProviderInterface::class),
      $resolver,
    );
  }

  /**
   * Creates a main request event for the request under test.
   */
  private function requestEvent(Request $request): RequestEvent {
    return new RequestEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
    );
  }

}
