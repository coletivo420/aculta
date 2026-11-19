<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\PublicForumFrontNavigationHooks;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\PathProcessor\SiteFrontPathProcessor;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests discovery tabs on the public Forum root. */
#[Group('aculta_forum')]
final class PublicForumFrontNavigationHooksTest extends UnitTestCase {

  /**
   * The root uses sibling tasks and a safe `/` destination. */
  public function testNavigationUsesExistingLocalTasksAtThePurposeRoot(): void {
    $requestStack = new RequestStack();
    $request = Request::create('/');
    $request->attributes->set(SiteFrontPathProcessor::FRONT_ATTRIBUTE, TRUE);
    $requestStack->push($request);

    $taskBuild = [
      0 => [
        'new_topics' => ['#active' => TRUE],
        'unanswered_topics' => ['#active' => FALSE],
      ],
      1 => [
        'contextual_task' => ['#active' => FALSE],
      ],
    ];
    $localTaskManager = $this->createMock(LocalTaskManagerInterface::class);
    $localTaskManager->expects(self::once())
      ->method('getTasksBuild')
      ->with('view.aculta_forum_public_topics.page_new_topics', self::isInstanceOf(CacheableMetadata::class))
      ->willReturnCallback(static function (string $route, CacheableMetadata $cacheability) use ($taskBuild): array {
        $cacheability->addCacheContexts(['user.permissions']);
        return $taskBuild;
      });

    $view = $this->view();
    $hooks = new PublicForumFrontNavigationHooks(
      $localTaskManager,
      $requestStack,
      $this->siteManager('forum'),
    );
    $hooks->viewsPreRender($view);

    /** @var array<string, mixed> $navigation */
    $navigation = $view->attachment_before['aculta_forum_front_navigation'];
    self::assertSame('menu_local_tasks', $navigation['#theme']);
    /** @var array<string, mixed> $primary */
    $primary = $navigation['#primary'];
    /** @var array<string, mixed> $frontTask */
    $frontTask = $primary['aculta_forum.public_front'];
    /** @var array<string, mixed> $link */
    $link = $frontTask['#link'];
    /** @var \Drupal\Core\Url $url */
    $url = $link['url'];
    self::assertSame('base:/', $url->getUri());
    /** @var array<string, mixed> $attributes */
    $attributes = $url->getOption('attributes');
    self::assertSame(
      'page',
      $attributes['aria-current'],
    );
    self::assertTrue($frontTask['#active']);
    /** @var array<string, mixed> $newTopicsTask */
    $newTopicsTask = $primary['new_topics'];
    self::assertFalse($newTopicsTask['#active']);
    self::assertSame($taskBuild[1], $navigation['#secondary']);
    /** @var array{contexts: list<string>} $cache */
    $cache = $navigation['#cache'];
    self::assertEqualsCanonicalizing(
      ['url.path', 'url.site', 'user.permissions'],
      $cache['contexts'],
    );
    /** @var array{contexts: list<string>} $elementCache */
    $elementCache = $view->element['#cache'];
    self::assertSame($cache['contexts'], $elementCache['contexts']);
  }

  /**
   * Internal list pages and unrelated Views do not receive a second tab row. */
  public function testNavigationIsLimitedToMarkedForumFrontRequest(): void {
    foreach ([
      [$this->view('other'), '/'],
      [$this->view(), '/discussoes/novas'],
      [$this->view(), '/'],
    ] as [$view, $path]) {
      $requestStack = new RequestStack();
      $request = Request::create($path);
      if ($path === '/' && $view->id() === 'aculta_forum_public_topics') {
        $request->attributes->set(SiteFrontPathProcessor::FRONT_ATTRIBUTE, FALSE);
      }
      $requestStack->push($request);
      $localTaskManager = $this->createMock(LocalTaskManagerInterface::class);
      $localTaskManager->expects(self::never())->method('getTasksBuild');

      (new PublicForumFrontNavigationHooks(
        $localTaskManager,
        $requestStack,
        $this->siteManager('forum'),
      ))->viewsPreRender($view);

      self::assertArrayNotHasKey('aculta_forum_front_navigation', $view->attachment_before);
    }
  }

  /**
   * Confirms the callback is registered as a Core OOP hook. */
  public function testHookUsesCoreViewsPreRender(): void {
    $method = new \ReflectionMethod(PublicForumFrontNavigationHooks::class, 'viewsPreRender');
    $attributes = $method->getAttributes(Hook::class);

    self::assertCount(1, $attributes);
    self::assertSame('views_pre_render', $attributes[0]->newInstance()->hook);
  }

  /**
   * Creates a View mock for the requested display.
   */
  private function view(string $id = 'aculta_forum_public_topics'): ViewExecutable {
    $view = $this->getMockBuilder(ViewExecutable::class)->disableOriginalConstructor()->getMock();
    $view->expects(self::any())->method('id')->willReturn($id);
    $view->current_display = 'page_discussions';
    $view->attachment_before = [];
    $view->element = [];
    return $view;
  }

  /**
   * Creates a purpose manager for the requested purpose.
   */
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

}
