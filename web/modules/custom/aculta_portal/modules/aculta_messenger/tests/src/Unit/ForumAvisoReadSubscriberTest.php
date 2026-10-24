<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeReadInterface;
use Drupal\aculta_messenger\EventSubscriber\ForumAvisoReadSubscriber;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that opening a canonical Forum topic clears its site notice count.
 */
#[Group('aculta_messenger')]
final class ForumAvisoReadSubscriberTest extends UnitTestCase {

  /**
   * Handles the upcast Node entity Drupal exposes on canonical routes.
   */
  public function testCanonicalRouteWithConvertedNodeMarksTopicRead(): void {
    $this->assertCanonicalNodeMarksRead($this->createMockNode(42));
  }

  /**
   * Retains compatibility with a raw numeric route parameter.
   */
  public function testCanonicalRouteWithNumericNodeIdMarksTopicRead(): void {
    $this->assertCanonicalNodeMarksRead('42');
  }

  /**
   * Ignores other routes and anonymous visitors.
   */
  public function testOtherRoutesAndAnonymousVisitorsAreIgnored(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(TRUE);
    $notices = $this->createMock(ForumNoticeReadInterface::class);
    $notices->expects(self::never())->method('marcarLido');
    $subscriber = new ForumAvisoReadSubscriber($account, $notices);
    $kernel = $this->createMock(HttpKernelInterface::class);

    $other_route = Request::create('/user/1');
    $other_route->attributes->set('_route', 'entity.user.canonical');
    $subscriber->onRequest(new RequestEvent($kernel, $other_route, HttpKernelInterface::MAIN_REQUEST));

    $canonical = Request::create('/node/42');
    $canonical->attributes->set('_route', 'entity.node.canonical');
    $canonical->attributes->set('node', $this->createMockNode(42));
    $subscriber->onRequest(new RequestEvent($kernel, $canonical, HttpKernelInterface::MAIN_REQUEST));
  }

  /**
   * Asserts that a canonical topic route delegates its ID to AvisoService.
   */
  private function assertCanonicalNodeMarksRead(NodeInterface|string $node): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('id')->willReturn('17');
    $notices = $this->createMock(ForumNoticeReadInterface::class);
    $notices->expects(self::once())->method('marcarLido')->with(17, 42);
    $subscriber = new ForumAvisoReadSubscriber($account, $notices);
    $request = Request::create('/node/42');
    $request->attributes->set('_route', 'entity.node.canonical');
    $request->attributes->set('node', $node);

    $subscriber->onRequest(new RequestEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
    ));
  }

  /**
   * Creates a node double with the requested ID.
   */
  private function createMockNode(int $nid): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn((string) $nid);
    return $node;
  }

}
