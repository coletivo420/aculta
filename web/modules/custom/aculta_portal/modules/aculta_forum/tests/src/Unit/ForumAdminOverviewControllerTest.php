<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Controller\ForumAdminOverviewController;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the central Forum administration overview and redirects.
 */
#[Group('aculta_forum')]
final class ForumAdminOverviewControllerTest extends UnitTestCase {

  /**
   * The central page shows Forum-only metrics and explains the context split.
   */
  public function testOverviewShowsPublicForumSummaryAndContextBoundaries(): void {
    [$controller, $topicQuery, $categoryQuery] = $this->controller();
    $controller->setStringTranslation($this->createMock(TranslationInterface::class));
    $topicQuery->expects(self::once())->method('accessCheck')->with(TRUE)->willReturnSelf();
    $categoryQuery->expects(self::once())->method('accessCheck')->with(TRUE)->willReturnSelf();
    $topicQuery->expects(self::exactly(3))->method('condition')->willReturnCallback(
      function (string $field, mixed $value) use ($topicQuery): QueryInterface {
        $expected = [
          'type' => 'aculta_forum_topic',
          'status' => NodeInterface::PUBLISHED,
          'field_domain_source.target_id' => 'forum_aculta_org',
        ];
        self::assertArrayHasKey($field, $expected);
        self::assertSame($expected[$field], $value);
        return $topicQuery;
      },
    );
    $categoryQuery->expects(self::once())->method('condition')
      ->with('vid', 'aculta_forum_categories')->willReturnSelf();

    $build = $controller->overview();

    self::assertSame('container', $build['#type']);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertSame(60, $build['#cache']['max-age']);
    self::assertSame(2, count($build['statistics']['#rows']));
    self::assertSame('12', $build['statistics']['#rows'][0]['data'][1]['data']['#plain_text']);
    self::assertSame('7', $build['statistics']['#rows'][1]['data'][1]['data']['#plain_text']);
    self::assertArrayNotHasKey('contexts', $build);
    self::assertCount(2, $build['public_links']['#items']);
    self::assertSame('https://forum.test/', $build['public_links']['#items'][0]['#url']->getUri());
    self::assertArrayNotHasKey('administrative_tools', $build);
    self::assertContains('node_list:aculta_forum_topic', $build['#cache']['tags']);
    self::assertContains('taxonomy_term_list:aculta_forum_categories', $build['#cache']['tags']);

  }

  /**
   * Administrative tool paths return locally to the central panel.
   */
  public function testLegacyAdminPathsRedirectToCentralPanel(): void {
    [$controller] = $this->controller();
    $response = $controller->redirectToOverview();

    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    self::assertSame('/painel-administrativo/forum', $response->getTargetUrl());
    self::assertSame(302, $response->getStatusCode());
  }

  /**
   * Creates the controller with isolated entity-query and Domain collaborators.
   *
   * @return array{0: \Drupal\aculta_forum\Controller\ForumAdminOverviewController, 1: \Drupal\Core\Entity\Query\QueryInterface, 2: \Drupal\Core\Entity\Query\QueryInterface}
   *   Controller and its two count queries.
   */
  private function controller(): array {
    $forumDomain = $this->createMock(DomainInterface::class);
    $forumDomain->method('id')->willReturn('forum_aculta_org');
    $forumDomain->method('getPath')->willReturn('https://forum.test');
    $forumDomain->method('getCacheContexts')->willReturn([]);
    $forumDomain->method('getCacheTags')->willReturn(['config:domain.record.forum_aculta_org']);
    $forumDomain->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);

    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('forum_aculta_org')->willReturn($forumDomain);

    $topicQuery = $this->query(7);
    $categoryQuery = $this->query(12);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($topicQuery);
    $taxonomyStorage = $this->createMock(EntityStorageInterface::class);
    $taxonomyStorage->method('getQuery')->willReturn($categoryQuery);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['domain', $domainStorage],
      ['node', $nodeStorage],
      ['taxonomy_term', $taxonomyStorage],
    ]);

    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    $purposes = new DomainPurposeManager(
      $entities,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return [new ForumAdminOverviewController($entities, $purposes), $topicQuery, $categoryQuery];
  }

  /**
   * Creates a chained EntityQuery mock for count results.
   */
  private function query(int $count): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    return $query;
  }

}
