<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_messenger\Entity\PrivateMessage;
use Drupal\aculta_messenger\Entity\PrivateMessageThread;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AnonymousUserSession;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Exercises the Core route access checks on Messenger's real POST route.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class PrivateMessageRouteAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'aculta_messenger',
    'flag',
    'content_moderation',
    'pathauto',
    'path_alias',
    'aculta_portal',
    'key',
    'message_notify',
    'message_subscribe',
    'node',
    'datetime',
    'menu_ui',
    'aculta_user',
    'aculta_mailer',
    'aculta_mail_confirmer',
    'aculta_forum',
    'site_alert',
    'aculta_file_uploader',
    'file',
    'token',
    'image',
    'crop',
    'image_widget_crop',
    'ctools',
    'ctools_views',
    'aculta_image',
    'profile',
    'views',
    'text_with_summary',
    'vvjs',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'address',
    'aculta_portal_cep',
    'login_emailusername',
    'social_auth',
    'group',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_store',
    'commerce_order',
    'commerce_payment',
    'physical',
    'taxonomy',
    'options',
    'media',
    'path',
    'metatag',
    'schema_metatag',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_organization',
    'schema_product',
    'schema_web_site',
    'schema_web_page',
    'search_api_db',
    'commerce_price',
    'commerce_product',
    'state_machine',
    'migrate',
    'entity_usage',
    'comment',
    'social_api',
    'workflows',
    'entity_reference_revisions',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('private_message_thread');
    $this->installEntitySchema('private_message');
    $this->installSchema('aculta_user', ['aculta_user_nickname']);
    $this->installEntitySchema('flagging');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig('aculta_user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installSchema('aculta_messenger', ['aculta_pm_thread_history']);
    $this->installConfig(['system', 'user', 'filter']);
    $this->grantMessagingPermissions();
  }

  /**
   * Shared email settings require Portal access, not permission to start chats.
   */
  public function testEmailPreferencesRouteDoesNotRequirePrivateMessaging(): void {
    $portalRole = Role::create(['id' => 'portal_email_settings', 'label' => 'Portal email settings']);
    $portalRole->grantPermission('access aculta portal');
    $portalRole->save();
    $portalMember = User::create([
      'name' => 'portal_email_member',
      'mail' => 'portal-email@example.invalid',
      'status' => 1,
      'roles' => ['portal_email_settings'],
    ]);
    $portalMember->save();
    $memberWithoutPortalAccess = $this->createMember('without_portal_email_access');

    [$routeName, $route] = $this->productionEmailPreferencesRoute();
    $routeMatch = new RouteMatch($routeName, $route);
    $request = Request::create('/configuracoes/email', 'GET');
    $access = $this->container->get('access_manager');

    self::assertTrue(
      $access->check($routeMatch, $portalMember, $request),
      'A Portal member can manage notice preferences without permission to start private conversations.',
    );
    self::assertFalse($access->check($routeMatch, $memberWithoutPortalAccess, $request));
    self::assertFalse($access->check($routeMatch, new AnonymousUserSession(), $request));
  }

  /**
   * Requires both thread membership and a valid Core route token.
   */
  public function testReplyRouteChecksMembershipCsrfAndPostMethod(): void {
    $member = $this->createMember('route_member');
    $otherMember = $this->createMember('route_other_member');
    $stranger = $this->createMember('route_stranger');
    $thread = PrivateMessageThread::create([
      'members' => [(int) $member->id(), (int) $otherMember->id()],
      'subject' => 'Protected conversation',
    ]);
    $thread->save();
    $message = PrivateMessage::create([
      'owner' => (int) $member->id(),
      'message' => ['value' => 'Existing message.', 'format' => 'plain_text'],
    ]);
    $message->save();
    $thread->addMessage($message);
    $thread->save();

    [$routeName, $route, $path] = $this->productionReplyRoute((int) $thread->id());
    self::assertSame(['POST'], $route->getMethods());
    $routeMatch = new RouteMatch(
      $routeName,
      $route,
      ['private_message_thread' => $thread],
      ['private_message_thread' => (string) $thread->id()],
    );
    $access = $this->container->get('access_manager');
    $csrf = $this->container->get('csrf_token');

    // The route's _csrf_token requirement is enforced by access_check.csrf.
    $csrfCheck = $this->container->get('access_check.csrf');
    $missingToken = Request::create('/' . $path, 'POST');
    self::assertTrue($csrfCheck->access($route, $missingToken, $routeMatch)->isForbidden());
    $invalidToken = Request::create('/' . $path . '?token=invalid', 'POST');
    self::assertTrue($csrfCheck->access($route, $invalidToken, $routeMatch)->isForbidden());
    $validToken = Request::create('/' . $path . '?token=' . rawurlencode($csrf->get($path)), 'POST');
    self::assertTrue($csrfCheck->access($route, $validToken, $routeMatch)->isAllowed());
    self::assertTrue($access->check($routeMatch, $member, $validToken));

    $validTokenForStranger = Request::create('/' . $path . '?token=' . rawurlencode($csrf->get($path)), 'POST');
    self::assertFalse(
      $access->check($routeMatch, $stranger, $validTokenForStranger),
      'A valid CSRF token does not grant access to a thread the account does not belong to.',
    );
  }

  /**
   * Loads the production route and runs Drupal's tagged access checks.
   *
   * @return array
   *   Route name, route object, and path with its thread parameter replaced.
   */
  private function productionReplyRoute(int $threadId): array {
    $routeFile = dirname(__DIR__, 3) . '/aculta_messenger.routing.yml';
    $definitions = Yaml::decode(file_get_contents($routeFile));
    $name = 'aculta_messenger.drawer_reply';
    $definition = $definitions[$name];
    $route = new Route(
      $definition['path'],
      $definition['defaults'] ?? [],
      $definition['requirements'] ?? [],
      $definition['options'] ?? [],
      '',
      [],
      $definition['methods'] ?? [],
    );
    $collection = new RouteCollection();
    $collection->add($name, $route);
    // The tagged access checker provider is private in compiled Drupal
    // containers. Use the instance owned by the public AccessManager service.
    $accessManager = $this->container->get('access_manager');
    $providerProperty = new \ReflectionProperty($accessManager, 'checkProvider');
    $providerProperty->getValue($accessManager)->setChecks($collection);
    $route = $collection->get($name);
    $path = str_replace('{private_message_thread}', (string) $threadId, ltrim($definition['path'], '/'));
    return [$name, $route, $path];
  }

  /**
   * Loads the production email-preferences route with Core access checks.
   *
   * @return array{string, \Symfony\Component\Routing\Route}
   *   Route name and route definition.
   */
  private function productionEmailPreferencesRoute(): array {
    $routeFile = dirname(__DIR__, 3) . '/aculta_messenger.routing.yml';
    $definitions = Yaml::decode(file_get_contents($routeFile));
    $name = 'aculta_messenger.communication_settings';
    $definition = $definitions[$name];
    $route = new Route(
      $definition['path'],
      $definition['defaults'] ?? [],
      $definition['requirements'] ?? [],
      $definition['options'] ?? [],
      '',
      [],
      $definition['methods'] ?? [],
    );
    $collection = new RouteCollection();
    $collection->add($name, $route);
    $accessManager = $this->container->get('access_manager');
    $providerProperty = new \ReflectionProperty($accessManager, 'checkProvider');
    $providerProperty->getValue($accessManager)->setChecks($collection);
    return [$name, $collection->get($name)];
  }

  /**
   * Grants the authenticated role the production route permissions.
   */
  private function grantMessagingPermissions(): void {
    $role = Role::load(Role::AUTHENTICATED_ID);
    $role->grantPermission('use private messaging system');
    $role->grantPermission('access user profiles');
    $role->save();
  }

  /**
   * Creates a member with the authenticated role.
   */
  private function createMember(string $name): User {
    $account = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
    ]);
    $account->save();
    return $account;
  }

}
