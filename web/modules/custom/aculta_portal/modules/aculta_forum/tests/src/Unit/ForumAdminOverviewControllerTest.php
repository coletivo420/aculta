<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\Controller\ForumAdminOverviewController;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the central Forum administration overview without route aliases.
 */
#[Group('aculta_forum')]
final class ForumAdminOverviewControllerTest extends UnitTestCase {

  /**
   * The central page shows Forum-only metrics and explains the context split.
   */
  public function testOverviewShowsPublicForumSummaryAndContextBoundaries(): void {
    [$controller, $topicQuery, $categoryQuery] = $this->controller();
    $controller->setStringTranslation($this->createMock(TranslationInterface::class));
    $topicQuery->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $categoryQuery->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
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

    self::assertSame('container', $build['#type'] ?? NULL);
    $cache = $build['#cache'] ?? NULL;
    self::assertIsArray($cache);
    $contexts = $cache['contexts'] ?? NULL;
    self::assertIsArray($contexts);
    self::assertContains('user.permissions', $contexts);
    self::assertSame(60, $cache['max-age'] ?? NULL);
    $statistics = $build['statistics'] ?? NULL;
    self::assertIsArray($statistics);
    $rows = $statistics['#rows'] ?? NULL;
    self::assertIsArray($rows);
    self::assertCount(2, $rows);
    self::assertSame('12', $this->plainTextFromRow($rows[0] ?? NULL));
    self::assertSame('7', $this->plainTextFromRow($rows[1] ?? NULL));
    self::assertArrayNotHasKey('contexts', $build);
    $publicLinks = $build['public_links'] ?? NULL;
    self::assertIsArray($publicLinks);
    $links = $publicLinks['#items'] ?? NULL;
    self::assertIsArray($links);
    self::assertCount(2, $links);
    $firstLink = $links[0] ?? NULL;
    self::assertIsArray($firstLink);
    $url = $firstLink['#url'] ?? NULL;
    self::assertInstanceOf(Url::class, $url);
    self::assertSame('https://forum.test/', $url->getUri());
    self::assertArrayNotHasKey('administrative_tools', $build);
    $tags = $cache['tags'] ?? NULL;
    self::assertIsArray($tags);
    self::assertContains('node_list:aculta_forum_topic', $tags);
    self::assertContains('taxonomy_term_list:aculta_forum_categories', $tags);

  }

  /**
   * Creates the controller with isolated entity-query and Domain collaborators.
   *
   * @return array{
   *   0: ForumAdminOverviewController,
   *   1: QueryInterface&MockObject,
   *   2: QueryInterface&MockObject
   * }
   *   Controller and its two count queries.
   */
  private function controller(): array {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    \Drupal::setContainer($container);

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
    $purposes = new DomainSiteManager(
      $entities,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $scopeResolver = $this->createMock(ForumAnalyticsScopeResolver::class);
    $scopeResolver->method('restrictedTopicIds')->willReturn([]);
    $scopeResolver->method('restrictedCategoryIds')->willReturn([]);

    return [new ForumAdminOverviewController($entities, $purposes, $scopeResolver), $topicQuery, $categoryQuery];
  }

  /**
   * Creates a chained EntityQuery mock for count results.
   */
  private function query(int $count): QueryInterface&MockObject {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    return $query;
  }

  /**
   * Reads the rendered text from one statistics row after validating its shape.
   */
  private function plainTextFromRow(mixed $row): string {
    self::assertIsArray($row);
    $data = $row['data'] ?? NULL;
    self::assertIsArray($data);
    $cell = $data[1] ?? NULL;
    self::assertIsArray($cell);
    $cellData = $cell['data'] ?? NULL;
    self::assertIsArray($cellData);
    $plainText = $cellData['#plain_text'] ?? NULL;
    self::assertIsString($plainText);

    return $plainText;
  }

}
