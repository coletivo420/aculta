<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\EventSubscriber\ForumSearchQuerySubscriber;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the public Forum search purpose boundary.
 */
#[Group('aculta_forum')]
final class ForumSearchQuerySubscriberTest extends UnitTestCase {

  /**
   * Queries for other indexes are unaffected.
   */
  public function testOtherSearchIndexesAreUnaffected(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('aculta_conteudo');
    $query = $this->createMock(QueryInterface::class);
    $query->method('getIndex')->willReturn($index);
    $query->method('addCondition')->willReturn($query);
    $query->expects(self::never())->method('addCondition');
    $purpose = $this->domainPurposeManager(NULL, NULL);

    (new ForumSearchQuerySubscriber($purpose))->onQueryPreExecute(new QueryPreExecuteEvent($query));
  }

  /**
   * Restricts Forum searches to the canonical Domain for that purpose.
   */
  public function testForumPurposeIsFilteredByItsDomainEntity(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
    $purpose = $this->domainPurposeManager($domain, $domain);
    $query = $this->forumQuery();
    $query->expects(self::once())->method('addCondition')->with('forum_search_domain', 'forum_aculta_org')->willReturn($query);

    (new ForumSearchQuerySubscriber($purpose))->onQueryPreExecute(new QueryPreExecuteEvent($query));
  }

  /**
   * A request on a non-Forum purpose cannot see public Forum search results.
   */
  public function testWrongPurposeCannotSearch(): void {
    $forumDomain = $this->createMock(DomainInterface::class);
    $forumDomain->method('id')->willReturn('forum_aculta_org');
    $mainDomain = $this->createMock(DomainInterface::class);
    $mainDomain->method('id')->willReturn('aculta_org');
    $purpose = $this->domainPurposeManager($forumDomain, $mainDomain);
    $query = $this->forumQuery();
    $query->expects(self::once())->method('addCondition')->with('forum_search_domain', '__forum_scope_denied__')->willReturn($query);

    (new ForumSearchQuerySubscriber($purpose))->onQueryPreExecute(new QueryPreExecuteEvent($query));
  }

  /**
   * A missing canonical Forum Domain fails closed.
   */
  public function testMissingForumDomainCannotSearch(): void {
    $purpose = $this->domainPurposeManager(NULL, NULL);
    $query = $this->forumQuery();
    $query->expects(self::once())->method('addCondition')->with('forum_search_domain', '__forum_scope_denied__')->willReturn($query);

    (new ForumSearchQuerySubscriber($purpose))->onQueryPreExecute(new QueryPreExecuteEvent($query));
  }

  /**
   * Creates a mock query for the Forum index.
   */
  private function forumQuery(): QueryInterface {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('aculta_forum');
    $query = $this->createMock(QueryInterface::class);
    $query->method('getIndex')->willReturn($index);
    $query->method('addCondition')->willReturn($query);
    return $query;
  }

  /**
   * Constructs the final purpose manager with mocked Drupal collaborators.
   */
  private function domainPurposeManager(
    ?DomainInterface $forum,
    ?DomainInterface $active,
  ): DomainPurposeManager {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('forum_aculta_org')->willReturn($forum);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($storage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($active);
    return new DomainPurposeManager(
      $entityTypeManager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
