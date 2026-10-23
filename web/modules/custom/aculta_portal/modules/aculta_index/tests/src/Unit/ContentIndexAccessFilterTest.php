<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_forum\ContentIndex\ForumContentIndexProvider;
use Drupal\aculta_wiki\ContentIndex\WikiContentIndexProvider;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessPolicyProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests original entity access revalidation for catalog candidates.
 *
 * @group aculta_index
 */
final class ContentIndexAccessFilterTest extends UnitTestCase {

  /**
   * Tests only allowed current translations are returned with cache metadata.
   */
  public function testFiltersDeniedEntitiesAndMergesCacheability(): void {
    $account = $this->createMock(AccountInterface::class);
    $allowedTranslation = $this->translation(AccessResult::allowed()->addCacheContexts(['user.permissions']), ['node:1']);
    $deniedTranslation = $this->translation(AccessResult::forbidden()->addCacheContexts(['user.roles']), ['node:2']);
    $entities = [
      '1' => $this->sourceEntity($allowedTranslation),
      '2' => $this->sourceEntity($deniedTranslation),
    ];
    $manager = $this->entityManager($entities);

    $result = (new ContentIndexAccessFilter($manager))->filter([
      ['entity_type' => 'node', 'entity_id' => '1', 'langcode' => 'pt-br'],
      ['entity_type' => 'node', 'entity_id' => '2', 'langcode' => 'pt-br'],
    ], $account);

    self::assertSame([$allowedTranslation], $result->entities);
    self::assertContains('user.permissions', $result->cacheability->getCacheContexts());
    self::assertContains('user.roles', $result->cacheability->getCacheContexts());
    self::assertContains('node:1', $result->cacheability->getCacheTags());
    self::assertContains('node:2', $result->cacheability->getCacheTags());
  }

  /**
   * Tests a missing original entity fails closed and adds a list cache tag.
   */
  public function testMissingEntityIsDeniedAndListTagIsAdded(): void {
    $manager = $this->entityManager([]);
    $result = (new ContentIndexAccessFilter($manager))->filter([
      ['entity_type' => 'node', 'entity_id' => '404', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertSame([], $result->entities);
    self::assertContains('node_list', $result->cacheability->getCacheTags());
  }

  /**
   * Tests a stale candidate with no enabled provider fails closed.
   */
  public function testUnknownProviderCandidateIsDenied(): void {
    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->method('bundle')->willReturn('orphan_bundle');
    $translation->method('getCacheContexts')->willReturn([]);
    $translation->method('getCacheMaxAge')->willReturn(-1);
    $translation->expects(self::never())->method('access');
    $manager = $this->entityManager(['1' => $this->sourceEntity($translation)]);

    $result = (new ContentIndexAccessFilter($manager, new ContentIndexProviderRegistry()))->filter([
      ['entity_type' => 'node', 'entity_id' => '1', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertSame([], $result->entities);
  }

  /**
   * Provider policy can further restrict an otherwise viewable entity.
   */
  public function testProviderPolicyIsCombinedWithEntityAccess(): void {
    $translation = $this->translation(AccessResult::allowed(), ['node:1']);
    $translation->method('bundle')->willReturn('lms_course');
    $provider = new class() implements ContentIndexProviderInterface, ContentIndexAccessPolicyProviderInterface {

      /** {@inheritdoc} */
      public function getProviderId(): string {
        return 'lms_course';
      }

      /** {@inheritdoc} */
      public function getProjectionVersion(): int {
        return 1;
      }

      /** {@inheritdoc} */
      public function getSupportedEntityTypes(): array {
        return ['group' => ['lms_course']];
      }

      /** {@inheritdoc} */
      public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
        return NULL;
      }

      /** {@inheritdoc} */
      public function checkAccess(ContentEntityInterface $entity, AccountInterface $account): AccessResultInterface {
        return AccessResult::forbidden()->addCacheContexts(['user.group_permissions']);
      }

    };
    $manager = $this->entityManagerWithType('group', $translation);
    $result = (new ContentIndexAccessFilter($manager, new ContentIndexProviderRegistry([$provider])))->filter([
      ['entity_type' => 'group', 'entity_id' => '1', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertSame([], $result->entities);
    self::assertContains('user.group_permissions', $result->cacheability->getCacheContexts());
  }

  /**
   * Tests the access filter bounds candidates to one page.
   */
  public function testRejectsOversizedCandidatePage(): void {
    $candidates = array_fill(0, 101, [
      'entity_type' => 'node',
      'entity_id' => '1',
      'langcode' => 'pt-br',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    (new ContentIndexAccessFilter($this->createMock(EntityTypeManagerInterface::class)))
      ->filter($candidates, $this->createMock(AccountInterface::class));
  }

  /**
   * Tests provider access purpose wraps Entity Access and restores the domain.
   */
  public function testChecksAccessInProviderPurposeAndRestoresActiveDomain(): void {
    $previousDomain = $this->createMock(DomainInterface::class);
    $forumDomain = $this->createMock(DomainInterface::class);
    $activeDomain = $previousDomain;
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturnCallback(static function () use (&$activeDomain): DomainInterface {
      return $activeDomain;
    });
    $negotiator->method('setActiveDomain')->willReturnCallback(static function (DomainInterface $domain) use (&$activeDomain): void {
      $activeDomain = $domain;
    });

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->with(['1'])->willReturn([
      '1' => $this->purposeTranslation('aculta_forum_topic', $negotiator, $forumDomain, 'forum-topic:1'),
    ]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['node_list']);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('forum_aculta_org')->willReturn($forumDomain);
    $manager->method('getStorage')->willReturnCallback(static fn (string $type) => $type === 'domain' ? $domainStorage : $storage);
    $purposeManager = new DomainPurposeManager(
      $manager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $provider = new ForumContentIndexProvider();
    $registry = new ContentIndexProviderRegistry([$provider]);

    $result = (new ContentIndexAccessFilter($manager, $registry, $purposeManager))->filter([
      ['entity_type' => 'node', 'entity_id' => '1', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertCount(1, $result->entities);
    self::assertSame($previousDomain, $activeDomain);
    self::assertContains('domain', $result->cacheability->getCacheContexts());
  }

  /**
   * Wiki Entity Access runs in the canonical Wiki purpose and restores Domain.
   */
  public function testChecksWikiAccessInCanonicalPurpose(): void {
    $previousDomain = $this->createMock(DomainInterface::class);
    $wikiDomain = $this->createMock(DomainInterface::class);
    $activeDomain = $previousDomain;
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturnCallback(static function () use (&$activeDomain): DomainInterface {
      return $activeDomain;
    });
    $negotiator->method('setActiveDomain')->willReturnCallback(static function (DomainInterface $domain) use (&$activeDomain): void {
      $activeDomain = $domain;
    });

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->with(['1'])->willReturn([
      '1' => $this->purposeTranslation('wiki_entry', $negotiator, $wikiDomain, 'wiki-entry:1'),
    ]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['node_list']);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->expects(self::once())->method('load')->with('wiki420_aculta_org')->willReturn($wikiDomain);
    $manager->method('getStorage')->willReturnCallback(static fn (string $type) => $type === 'domain' ? $domainStorage : $storage);
    $purposeManager = new DomainPurposeManager(
      $manager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    $result = (new ContentIndexAccessFilter(
      $manager,
      new ContentIndexProviderRegistry([new WikiContentIndexProvider()]),
      $purposeManager,
    ))->filter([
      ['entity_type' => 'node', 'entity_id' => '1', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertCount(1, $result->entities);
    self::assertSame($previousDomain, $activeDomain);
    self::assertContains('domain', $result->cacheability->getCacheContexts());
  }

  /**
   * A missing canonical purpose domain denies access without fallback.
   */
  public function testMissingProviderPurposeDomainFailsClosed(): void {
    $translation = $this->createMock(NodeInterface::class);
    $translation->method('bundle')->willReturn('aculta_forum_topic');
    $translation->method('getCacheContexts')->willReturn([]);
    $translation->method('getCacheMaxAge')->willReturn(-1);
    $translation->expects(self::never())->method('access');

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('loadMultiple')->with(['1'])->willReturn([
      '1' => $this->sourceEntity($translation),
    ]);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->expects(self::once())->method('load')->with('forum_aculta_org')->willReturn(NULL);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['node_list']);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $manager->method('getStorage')->willReturnCallback(
      static fn (string $type) => $type === 'domain' ? $domainStorage : $nodeStorage,
    );
    $purposeManager = new DomainPurposeManager(
      $manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $provider = new ForumContentIndexProvider();

    $result = (new ContentIndexAccessFilter(
      $manager,
      new ContentIndexProviderRegistry([$provider]),
      $purposeManager,
    ))->filter([
      ['entity_type' => 'node', 'entity_id' => '1', 'langcode' => 'pt-br'],
    ], $this->createMock(AccountInterface::class));

    self::assertSame([], $result->entities);
    self::assertContains('domain', $result->cacheability->getCacheContexts());
  }

  /**
   * Creates a source entity mock with one translation.
   */
  private function sourceEntity(ContentEntityInterface $translation): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('hasTranslation')->with('pt-br')->willReturn(TRUE);
    $entity->method('getTranslation')->with('pt-br')->willReturn($translation);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $entity->method('getCacheTags')->willReturn(['source_list']);
    return $entity;
  }

  /**
   * Creates a forum translation whose access handler requires Forum purpose.
   */
  private function purposeTranslation(string $bundle, DomainNegotiatorInterface $negotiator, DomainInterface $purposeDomain, string $cacheTag): NodeInterface {
    $translation = $this->createMock(NodeInterface::class);
    $translation->method('bundle')->willReturn($bundle);
    $translation->method('hasTranslation')->with('pt-br')->willReturn(TRUE);
    $translation->method('getTranslation')->with('pt-br')->willReturn($translation);
    $translation->method('getCacheContexts')->willReturn([]);
    $translation->method('getCacheMaxAge')->willReturn(-1);
    $translation->method('access')->willReturnCallback(static function () use ($negotiator, $purposeDomain): AccessResult {
      return $negotiator->getActiveDomain() === $purposeDomain ? AccessResult::allowed() : AccessResult::forbidden();
    });
    $translation->method('getCacheTags')->willReturn([$cacheTag]);
    return $translation;
  }

  /**
   * Creates a translated entity mock with a configured access result.
   *
   * @param \Drupal\Core\Access\AccessResult $access
   *   Result returned by the entity access handler.
   * @param list<string> $cacheTags
   *   Source cache tags.
   */
  private function translation(AccessResult $access, array $cacheTags): ContentEntityInterface {
    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->method('access')->with('view', self::anything(), TRUE)->willReturn($access);
    $translation->method('getCacheContexts')->willReturn([]);
    $translation->method('getCacheMaxAge')->willReturn(-1);
    $translation->method('getCacheTags')->willReturn($cacheTags);
    return $translation;
  }

  /**
   * Creates the minimal entity type manager fixture.
   *
   * @param array<string, \Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Entities keyed by ID.
   */
  private function entityManager(array $entities): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($entities);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['node_list']);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $manager->method('getStorage')->with('node')->willReturn($storage);
    return $manager;
  }

  /**
   * Creates an entity type manager for one arbitrary entity type.
   */
  private function entityManagerWithType(string $type, ContentEntityInterface $translation): EntityTypeManagerInterface {
    $entity = $this->sourceEntity($translation);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn(['1' => $entity]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn([$type . '_list']);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with($type)->willReturn(TRUE);
    $manager->method('getDefinition')->with($type)->willReturn($entityType);
    $manager->method('getStorage')->with($type)->willReturn($storage);
    return $manager;
  }

}
