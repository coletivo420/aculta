<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\PublicForumTopicCreateActionHooks;
use Drupal\aculta_portal\Domain\DomainSiteManager;
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

  protected function setUp(): void {
    parent::setUp();
    // Url::toString() precisa do gerador de URL; o teste unitário fornece um falso.
    $generator = $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/forum/topico/novo');
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('url_generator', $generator);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests the action and its cache metadata when route access allows it. */
  public function testActionAppearsOnlyAfterRouteAccessAllowsIt(): void {
    $view = $this->view();
    $view->element['#cache'] = ['contexts' => ['url.query_args']];
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects(self::once())
      ->method('checkNamedRoute')
      ->with('aculta_forum.topic_composer', [], NULL, TRUE)
      ->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));

    $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->siteManager('forum'));
    $hooks->viewsPreRender($view);

    /** @var array<string, mixed> $create */
    $create = $view->attachment_before['aculta_forum_create_topic'];
    self::assertSame('link', $create['#type']);
    /** @var array<string, mixed> $attributes */
    $attributes = $create['#attributes'];
    self::assertSame('topico-criar', $attributes['data-action']);
    /** @var list<string> $classes */
    $classes = $attributes['class'];
    self::assertContains('use-htmx', $classes);
    self::assertSame('#aculta-topic-dialog-body', $attributes['data-hx-target']);
    /** @var array{library?: list<string>} $attached */
    $attached = $view->element['#attached'];
    self::assertContains('aculta_forum/topic-create-popup', $attached['library'] ?? []);
    /** @var array{contexts?: list<string>} $createCache */
    $createCache = $create['#cache'];
    self::assertSame(['user.permissions'], $createCache['contexts'] ?? []);
    /** @var array{contexts?: list<string>} $elementCache */
    $elementCache = $view->element['#cache'];
    self::assertEqualsCanonicalizing(['url.query_args', 'user.permissions'], $elementCache['contexts'] ?? []);
  }

  /**
   * Tests that denied route access omits the action but remains cacheable. */
  public function testActionIsOmittedWhenRouteAccessDeniesIt(): void {
    $view = $this->view();
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects(self::once())
      ->method('checkNamedRoute')
      ->willReturn(AccessResult::forbidden()->addCacheContexts(['user.permissions']));

    $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->siteManager('forum'));
    $hooks->viewsPreRender($view);

    self::assertArrayNotHasKey('aculta_forum_create_topic', $view->attachment_before);
    /** @var array{contexts?: list<string>} $elementCache */
    $elementCache = $view->element['#cache'];
    self::assertSame(['user.permissions'], $elementCache['contexts'] ?? []);
  }

  /**
   * Tests that unrelated Views and non-forum purposes are ignored. */
  public function testOtherViewOrPurposeIsIgnored(): void {
    $view = $this->view('other', 'page_discussions');
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->expects(self::never())->method('checkNamedRoute');

    $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->siteManager('main'));
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

      $hooks = new PublicForumTopicCreateActionHooks($access_manager, $this->siteManager($purpose));
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
  private function siteManager(string $purpose): DomainSiteManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn($purpose === 'forum' ? 'forum_aculta_org' : 'aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);

    return new DomainSiteManager(
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
