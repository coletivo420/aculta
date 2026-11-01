<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\PublicForumTopicCreateActionHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the access-aware action on the public discussion index.
 */
#[Group('aculta_forum')]
final class PublicForumTopicCreateActionHooksTest extends UnitTestCase {

  /**
   * Tests the action and its cache metadata when route access allows it. */
  public function testActionAppearsOnlyAfterRouteAccessAllowsIt(): void {
    $view = $this->view();
    $view->element['#cache']['contexts'] = ['url.query_args'];
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects(self::once())
      ->method('checkNamedRoute')
      ->with('aculta_forum.topic_add', [], NULL, TRUE)
      ->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));

    $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->domainPurposeManager('forum'));
    $hooks->viewsPreRender($view);

    self::assertSame('button-primary', $view->attachment_before['aculta_forum_create_topic']['#attributes']['data-portal-ui']);
    self::assertSame(['user.permissions'], $view->attachment_before['aculta_forum_create_topic']['#cache']['contexts']);
    self::assertEqualsCanonicalizing(['url.query_args', 'user.permissions'], $view->element['#cache']['contexts']);
  }

  /**
   * Tests that denied route access omits the action but remains cacheable. */
  public function testActionIsOmittedWhenRouteAccessDeniesIt(): void {
    $view = $this->view();
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects(self::once())
      ->method('checkNamedRoute')
      ->willReturn(AccessResult::forbidden()->addCacheContexts(['user.permissions']));

    $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->domainPurposeManager('forum'));
    $hooks->viewsPreRender($view);

    self::assertArrayNotHasKey('aculta_forum_create_topic', $view->attachment_before);
    self::assertSame(['user.permissions'], $view->element['#cache']['contexts']);
  }

  /**
   * Tests that unrelated Views and non-forum purposes are ignored. */
  public function testOtherViewOrPurposeIsIgnored(): void {
    $view = $this->view('other', 'page_discussions');
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects(self::never())->method('checkNamedRoute');

    $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->domainPurposeManager('main'));
    $hooks->viewsPreRender($view);

    self::assertSame([], $view->attachment_before);
  }

  /**
   * Tests that a different display and a different purpose are independently ignored. */
  public function testOtherDisplayAndPurposeAreIgnored(): void {
    foreach ([
      [$this->view('aculta_forum_public_topics', 'block_recent'), 'forum'],
      [$this->view(), 'main'],
    ] as [$view, $purpose]) {
      $access_manager = $this->createMock(AccessManagerInterface::class);
      $access_manager->expects(self::never())->method('checkNamedRoute');

      $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->domainPurposeManager($purpose));
      $hooks->viewsPreRender($view);

      self::assertSame([], $view->attachment_before);
    }
  }

  /**
   * Creates a View executable with the requested display identity. */
  private function view(string $id = 'aculta_forum_public_topics', string $display = 'page_discussions'): ViewExecutable {
    $view = $this->getMockBuilder(ViewExecutable::class)->disableOriginalConstructor()->getMock();
    $view->expects(self::any())->method('id')->willReturn($id);
    $view->current_display = $display;
    $view->attachment_before = [];
    $view->element = [];
    return $view;
  }

  /**
   * Creates a purpose manager backed by a test Domain negotiator. */
  private function domainPurposeManager(string $purpose): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn($purpose === 'forum' ? 'forum_aculta_org' : 'aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);

    return new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

  /**
   * Ensures the hook uses the Core OOP hook attribute. */
  public function testHookUsesCoreViewsPreRender(): void {
    $method = new \ReflectionMethod(PublicForumTopicCreateActionHooks::class, 'viewsPreRender');
    $attributes = $method->getAttributes(Hook::class);

    self::assertCount(1, $attributes);
    self::assertSame('views_pre_render', $attributes[0]->newInstance()->hook);
  }

}
