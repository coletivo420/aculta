<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\Core\Routing\RouteMatch;
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
    'private_message',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('private_message_thread');
    $this->installEntitySchema('private_message');
    $this->installSchema('private_message', ['pm_thread_history']);
    $this->installConfig(['system', 'user', 'filter']);
    $this->grantMessagingPermissions();
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

    $missingToken = Request::create('/' . $path, 'POST');
    self::assertFalse($access->check($routeMatch, $member, $missingToken));
    $invalidToken = Request::create('/' . $path . '?token=invalid', 'POST');
    self::assertFalse($access->check($routeMatch, $member, $invalidToken));
    $validToken = Request::create('/' . $path . '?token=' . rawurlencode($csrf->get($path)), 'POST');
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
