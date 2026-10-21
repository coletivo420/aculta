<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Unit;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Service\LinkDestinationResolver;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\link\LinkItemInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Domain-aware resolution of internal node destinations.
 *
 * @group aculta_links
 * @coversDefaultClass \Drupal\aculta_links\Service\LinkDestinationResolver
 */
final class LinkDestinationResolverTest extends UnitTestCase {

  /**
   * Resolves an accessible public node using its source Domain purpose.
   *
   * @covers ::resolve
   */
  public function testNodeTargetUsesItsDomainPurposeAndAnonymousAccess(): void {
    $uri_field = $this->createMock(LinkItemInterface::class);
    $uri_field->method('__get')->with('uri')->willReturn('entity:node/42');
    $uri_field->method('getUrl')->willReturn(Url::fromUri('entity:node/42'));
    $source_field = $this->createMock(FieldItemListInterface::class);
    $source_field->method('isEmpty')->willReturn(FALSE);
    $source_field->method('__get')->with('target_id')->willReturn('cursos_aculta_org');

    $node = $this->createMock(NodeInterface::class);
    $node->method('getCacheTags')->willReturn(['node:42']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    $node->method('id')->willReturn(42);
    $node->method('hasField')->with('field_domain_source')->willReturn(TRUE);
    $node->method('get')->with('field_domain_source')->willReturn($source_field);
    $node->method('isPublished')->willReturn(TRUE);
    $node->expects(self::once())
      ->method('access')
      ->with('view', self::isInstanceOf(AnonymousUserSession::class), TRUE)
      ->willReturn(AccessResult::allowed());

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getListCacheTags')->willReturn(['node_list']);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn($node);
    $storage->method('getEntityType')->willReturn($entity_type);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('getCacheTags')->willReturn(['config:domain.record.cursos_aculta_org']);
    $domain->method('getCacheContexts')->willReturn([]);
    $domain->method('getCacheMaxAge')->willReturn(-1);
    $domain->method('isHttps')->willReturn(TRUE);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->method('load')->with('cursos_aculta_org')->willReturn($domain);
    $entity_type_manager->method('getStorage')->willReturnCallback(
      static fn (string $id): EntityStorageInterface => $id === 'node' ? $storage : $domain_storage,
    );
    $domain_negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domain_negotiator->method('getActiveDomain')->willReturn(NULL);
    $domain_purpose = new DomainPurposeManager(
      $entity_type_manager,
      $domain_negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    $resolver = new LinkDestinationResolver($entity_type_manager, $domain_purpose);
    $resolution = $resolver->resolve($uri_field, $this->createMock(AccountInterface::class), TRUE);

    self::assertInstanceOf(Url::class, $resolution->url);
    self::assertSame('entity.node.canonical', $resolution->url?->getRouteName());
    self::assertSame(['node' => 42], $resolution->url?->getRouteParameters());
    self::assertContains('url.site', $resolution->cacheability->getCacheContexts());
    self::assertNotEmpty($resolution->cacheability->getCacheTags());
  }

}
