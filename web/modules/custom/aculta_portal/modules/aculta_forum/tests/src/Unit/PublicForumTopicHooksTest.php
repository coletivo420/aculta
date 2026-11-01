<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\PublicForumTopicHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Forum topic purpose assignment and fail-closed creation access.
 */
#[Group('aculta_forum')]
final class PublicForumTopicHooksTest extends UnitTestCase {

  /**
   * Tests that creation is forbidden if the canonical Forum domain is absent.
   */
  public function testCreationRequiresConfiguredForumDomain(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with('forum_aculta_org')->willReturn(NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')->with('domain')->willReturn($storage);
    $hooks = new PublicForumTopicHooks($this->domainPurposeManager($entity_type_manager));

    $result = $hooks->entityCreateAccess($this->createMock(AccountInterface::class), ['entity_type_id' => 'node'], 'aculta_forum_topic');

    self::assertNotNull($result);
    self::assertTrue($result->isForbidden());
    self::assertContains('config:domain.record.forum_aculta_org', $result->getCacheTags());
    self::assertContains('domain', $result->getCacheContexts());
  }

  /**
   * Tests neutral access when the canonical Forum domain is active.
   */
  public function testCreationIsScopedToTheForumPurpose(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
    $domain->method('getCacheContexts')->willReturn([]);
    $domain->method('getCacheTags')->willReturn(['config:domain.record.forum_aculta_org']);
    $domain->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('forum_aculta_org')->willReturn($domain);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('domain')->willReturn($storage);
    $domain_negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domain_negotiator->method('getActiveDomain')->willReturn($domain);
    $manager = new DomainPurposeManager(
      $entity_type_manager,
      $domain_negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $hooks = new PublicForumTopicHooks($manager);

    $result = $hooks->entityCreateAccess($this->createMock(AccountInterface::class), ['entity_type_id' => 'node'], 'aculta_forum_topic');

    self::assertNotNull($result);
    self::assertTrue($result->isNeutral());
    self::assertContains('domain', $result->getCacheContexts());
  }

  /**
   * Tests that a new topic receives the canonical Forum domain.
   */
  public function testNewTopicReceivesCanonicalForumDomain(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with('forum_aculta_org')->willReturn($domain);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')->with('domain')->willReturn($storage);
    $hooks = new PublicForumTopicHooks($this->domainPurposeManager($entity_type_manager));
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(TRUE);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('hasField')->with('field_domain_source')->willReturn(TRUE);
    $node->method('get')->with('field_domain_source')->willReturn($field);
    $node->expects(self::once())->method('set')->with('field_domain_source', ['target_id' => 'forum_aculta_org']);

    $hooks->nodePresave($node);
  }

  /**
   * Tests that an explicit domain assignment is preserved.
   */
  public function testExistingExplicitDomainSourceIsPreserved(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $domain_purpose = $this->domainPurposeManager($entity_type_manager);
    $hooks = new PublicForumTopicHooks($domain_purpose);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('hasField')->with('field_domain_source')->willReturn(TRUE);
    $node->method('get')->with('field_domain_source')->willReturn($field);
    $node->expects(self::never())->method('set');

    $hooks->nodePresave($node);
  }

  /**
   * Creates the purpose resolver with the provided entity manager.
   */
  private function domainPurposeManager(EntityTypeManagerInterface $entity_type_manager): DomainPurposeManager {
    return new DomainPurposeManager(
      $entity_type_manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
