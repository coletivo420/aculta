<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\Domain\SiteKeyRegistry;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the typed site-key resolver and Domain context restoration.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class DomainSiteManagerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'domain'];

  /**
   * Typed site resolver under test.
   *
   * @var \Drupal\aculta_portal\Domain\DomainSiteManager
   */
  private DomainSiteManager $sites;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->sites = new DomainSiteManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
      new SiteKeyRegistry(),
    );
  }

  /**
   * Tests canonical Domain ID resolution.
   */
  public function testDomainIdsResolveToTypedSiteKeys(): void {
    self::assertSame(SiteKey::Main, $this->sites->getSiteKeyForDomainId('aculta_org'));
    self::assertSame(SiteKey::Links, $this->sites->getSiteKeyForDomainId('links_aculta_org'));
    self::assertNull($this->sites->getSiteKeyForDomainId('unknown'));
  }

  /**
   * Tests typed Domain loading and path URL validation.
   */
  public function testGetDomainAndPathUrlUseTheTypedSiteKey(): void {
    Domain::create([
      'id' => 'loja_aculta_org',
      'hostname' => 'shop.example.invalid',
      'name' => 'Shop',
      'scheme' => 'https',
      'status' => TRUE,
    ])->save();

    self::assertSame('loja_aculta_org', $this->sites->getDomain(SiteKey::Shop)?->id());
    self::assertNull($this->sites->getDomain(SiteKey::Forum));
    self::assertNull($this->sites->pathUrl(SiteKey::Shop, '//attacker.invalid/path'));
  }

  /**
   * Tests Domain context restoration after running in another site.
   */
  public function testRunInSiteRestoresThePriorDomain(): void {
    foreach ([
      ['id' => 'aculta_org', 'hostname' => 'main.example.invalid', 'name' => 'Main'],
      ['id' => 'loja_aculta_org', 'hostname' => 'shop.example.invalid', 'name' => 'Shop'],
    ] as $values) {
      Domain::create($values + [
        'scheme' => 'https',
        'status' => TRUE,
      ])->save();
    }

    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $context = $negotiator->getContext();
    $context->setDomain(Domain::load('aculta_org'));
    $context->setNegotiated(TRUE);

    $result = $this->sites->runInSite(
      SiteKey::Shop,
      static fn (): ?string => $negotiator->getActiveDomain()?->id(),
    );

    self::assertSame('loja_aculta_org', $result);
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
  }

}
