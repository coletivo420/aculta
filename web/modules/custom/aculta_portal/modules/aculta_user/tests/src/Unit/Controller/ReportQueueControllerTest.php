<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Controller\ReportQueueController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the canonical redirect for the Forum report administration path.
 */
#[Group('aculta_user')]
final class ReportQueueControllerTest extends UnitTestCase {

  /**
   * The retired report queue path redirects locally to the Forum panel.
   */
  public function testReportQueuePathRedirectsToForumPanel(): void {
    $response = (new ReportQueueController())->view();

    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    self::assertSame('/painel-administrativo/forum', $response->getTargetUrl());
    self::assertSame(302, $response->getStatusCode());
  }

  /**
   * The retired path keeps its access requirement and accepts form submissions.
   */
  public function testReportQueueRoutePreservesAccessAndRedirectsPost(): void {
    $routing = Yaml::parseFile(dirname(__DIR__, 4) . '/aculta_user.routing.yml');
    $route = $routing['aculta_user.denuncias_forum'];

    self::assertSame('moderar denuncias do forum', $route['requirements']['_permission']);
    self::assertSame(['GET', 'HEAD', 'POST'], $route['methods']);
  }

  /**
   * Render structure carries neutral theme tokens without changing queue data.
   */
  public function testReportQueueUsesNeutralPresentationTokens(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $permission === 'moderar denuncias do forum',
    );
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    $container->set('entity_type.manager', $manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $queue = new class {

      /**
       * Returns an empty queue with a second page to exercise the pager.
       */
      public function getQueueItems(AccountInterface $account, string $purpose, string $status, int $page, int $limit): array {
        return ['items' => [], 'total_items' => 0, 'total_pages' => 2];
      }

    };
    $build = (new ReportQueueController($queue))->view(Request::create('/forum/reports'));

    self::assertSame('forum-report-queue', $build['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('class', $build['#attributes']);
    self::assertSame('forum-report-pager', $build['pager']['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('class', $build['pager']['#attributes']);
    self::assertSame(Cache::PERMANENT, $build['#cache']['max-age']);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertContains('url.query_args:page', $build['#cache']['contexts']);
    self::assertContains('aculta_ugc_report_list', $build['#cache']['tags']);
  }

}
