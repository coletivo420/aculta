<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\Domain\SiteKeyRegistry;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiationContext;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Exercises the primary site resolver against real mapping and mocks.
 *
 * The previous version asserted delegation to DomainPurposeManager, but the
 * manager is now the primary resolver: DomainNegotiator → Domain ID →
 * SiteKeyRegistry, with fail-closed URL helpers when no Domain is configured.
 */
#[Group('aculta_portal')]
final class DomainSiteManagerTest extends UnitTestCase {

  private const FORUM_DOMAIN = 'forum_aculta_org';

  /**
   * Resolves a site key from a configured Domain ID and rejects unknown IDs.
   */
  public function testSiteKeyForDomainIdUsesRegistry(): void {
    $manager = $this->manager();
    $this->assertSame(SiteKey::Forum, $manager->getSiteKeyForDomainId(self::FORUM_DOMAIN));
    $this->assertNull($manager->getSiteKeyForDomainId('dominio_desconhecido'));
  }

  /**
   * The current site key comes from the active Domain entity.
   */
  public function testCurrentSiteKeyUsesActiveDomainId(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn(self::FORUM_DOMAIN);
    $manager = $this->manager(activeDomain: $domain);

    $this->assertSame(SiteKey::Forum, $manager->getCurrentSiteKey());
  }

  /**
   * The visual resolver receives the active Drupal Domain ID.
   */
  public function testCurrentDomainIdUsesActiveDomain(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn(self::FORUM_DOMAIN);
    $this->assertSame(self::FORUM_DOMAIN, $this->manager(activeDomain: $domain)->getCurrentDomainId());
  }

  /**
   * There is no current Domain ID when negotiation has not selected one.
   */
  public function testCurrentDomainIdIsNullWithoutActiveDomain(): void {
    $this->assertNull($this->manager(activeDomain: NULL)->getCurrentDomainId());
  }

  /**
   * Without an active Domain there is no current site key.
   */
  public function testCurrentSiteKeyIsNullWithoutActiveDomain(): void {
    $this->assertNull($this->manager(activeDomain: NULL)->getCurrentSiteKey());
  }

  /**
   * Loads the Domain entity and rejects a non-Domain result.
   */
  public function testGetDomainLoadsConfiguredEntity(): void {
    $domain = $this->createMock(DomainInterface::class);
    $manager = $this->manager(loadedDomain: $domain);
    $this->assertSame($domain, $manager->getDomain(SiteKey::Forum));

    $notADomain = new \stdClass();
    $manager = $this->manager(loadedDomain: $notADomain);
    $this->assertNull($manager->getDomain(SiteKey::Forum));
  }

  /**
   * Leaves the context unchanged when the site has no Domain.
   */
  public function testRunInSiteReturnsNullWhenDomainIsMissing(): void {
    $manager = $this->manager(loadedDomain: NULL);
    $called = FALSE;
    $result = $manager->runInSite(SiteKey::Forum, static function () use (&$called): string {
      $called = TRUE;
      return 'resultado';
    });
    $this->assertNull($result);
    $this->assertFalse($called, 'O callback não roda sem domínio configurado.');
  }

  /**
   * Activates the target Domain and restores the previous one.
   */
  public function testRunInSiteSwapsAndRestoresDomainContext(): void {
    $previous = $this->createMock(DomainInterface::class);
    $target = $this->createMock(DomainInterface::class);
    $context = $this->createMock(DomainNegotiationContext::class);
    $context->expects($this->exactly(2))->method('setDomain');
    $context->expects($this->exactly(2))->method('setNegotiated')->with(TRUE);

    $manager = $this->manager(
      activeDomain: $previous,
      loadedDomain: $target,
      context: $context,
    );

    $this->assertSame('resultado', $manager->runInSite(SiteKey::Forum, static fn (): string => 'resultado'));
  }

  /**
   * URL helpers fail closed instead of inventing a host.
   */
  public function testUrlHelpersReturnNullWithoutDomain(): void {
    $manager = $this->manager(loadedDomain: NULL);

    $this->assertNull($manager->routeUrl(SiteKey::Forum, 'aculta_forum.page'));
    $this->assertNull($manager->canonicalRouteUrl(SiteKey::Forum, 'aculta_forum.page'));
    $this->assertNull($manager->canonicalPathUrl(SiteKey::Forum, '/forum'));
    // A protocol-relative path is rejected before any domain lookup.
    $this->assertNull($manager->canonicalPathUrl(SiteKey::Forum, '//evil.example'));
  }

  /**
   * Builds the resolver with the requested collaborator behaviour.
   */
  private function manager(
    mixed $activeDomain = NULL,
    mixed $loadedDomain = NULL,
    ?DomainNegotiationContext $context = NULL,
  ): DomainSiteManager {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($loadedDomain);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($storage);

    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($activeDomain);
    $negotiator->method('getContext')->willReturn($context ?? new DomainNegotiationContext());

    return new DomainSiteManager(
      $entityTypeManager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
      new SiteKeyRegistry(),
    );
  }

}
