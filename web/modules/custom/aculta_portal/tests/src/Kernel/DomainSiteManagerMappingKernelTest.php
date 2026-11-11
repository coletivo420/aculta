<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;
use Drupal\aculta_portal\Domain\SiteKey;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests purpose-to-domain mapping and active Domain restoration.
 *
 * Restoring the previous domain keeps the rest of the request in the right
 * context after runInSite() evaluates access for another purpose.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class DomainSiteManagerMappingKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = ['system', 'user', 'domain'];

  /**
   * Maps each purpose to its canonical Domain ID.
   */
  private const DOMAINS = [
    'main' => 'aculta_org',
    'account' => 'conta_aculta_org',
    'support' => 'apoio_aculta_org',
    'magazine' => 'revista420_aculta_org',
    'wiki' => 'wiki420_aculta_org',
    'shop' => 'loja_aculta_org',
    'courses' => 'cursos_aculta_org',
    'forum' => 'forum_aculta_org',
    'eventos' => 'eventos_aculta_org',
    'aculta_file_uploader',
    'file',
    'token',
    'image',
    'crop',
    'image_widget_crop',
  ];

  /**
   * Domain-purpose service under test. */
  private DomainSiteManager $manager;

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->manager = new DomainSiteManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
  }

  /**
   * Creates canonical Domain entities with test hostnames. */
  private function createDomains(): void {
    foreach (self::DOMAINS as $purpose => $id) {
      Domain::create([
        'id' => $id,
        'hostname' => $purpose . '.example.invalid',
        'name' => $purpose,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }
  }

  /**
   * Tests that every configured Domain ID resolves to its purpose. */
  public function testEveryCanonicalDomainIdMapsToItsPurpose(): void {
    foreach (self::DOMAINS as $purpose => $id) {
      $this->assertSame(
        $purpose,
        $this->manager->getSiteKeyValueForDomainId($id),
        "Domain $id deve mapear para $purpose.",
      );
    }
  }

  /**
   * Tests that unknown Domain IDs do not resolve to a purpose. */
  public function testUnknownDomainIdHasNoPurpose(): void {
    $this->assertNull($this->manager->getSiteKeyValueForDomainId('desconhecido_aculta_org'));
    $this->assertNull($this->manager->getSiteKeyValueForDomainId(''));
  }

  /**
   * Tests that a configured purpose returns its Domain entity. */
  public function testGetDomainReturnsEntityForPurpose(): void {
    $this->createDomains();
    $domain = $this->manager->getDomain(SiteKey::Shop);
    $this->assertNotNull($domain);
    $this->assertSame('loja_aculta_org', $domain->id());
  }

  /**
   * Tests that environment path URLs use the Deployer host. */
  public function testEnvironmentPathUrlUsesTheDeployerHostForTheActiveEnvironment(): void {
    $this->createDomains();
    $expectedHost = DeployerEnvironment::domainHost(dirname(DRUPAL_ROOT), 'eventos_aculta_org');
    $this->assertNotNull($expectedHost);
    $url = $this->manager->environmentPathUrl(SiteKey::Eventos, '/calendario');
    $this->assertNotNull($url);
    $this->assertSame('https://' . $expectedHost . '/calendario', $url->toString());
  }

  /**
   * Tests that missing purpose entities return NULL. */
  public function testGetDomainReturnsNullForUnknownPurposeOrMissingEntity(): void {
    $this->assertNull($this->manager->getDomain('inexistente'));
    // Purpose válido, mas o Domain ainda não existe no banco.
    $this->assertNull($this->manager->getDomain(SiteKey::Forum));
  }

  /**
   * Tests that runInSite restores the prior domain after the callback.
   */
  public function testRunInPurposeSwitchesToThePurposeDomainAndRestoresThePrevious(): void {
    $this->createDomains();
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $context = $negotiator->getContext();
    $context->setDomain(Domain::load('aculta_org'));
    $context->setNegotiated(TRUE);

    $inside = $this->manager->runInSite(
      'shop',
      static fn() => $negotiator->getActiveDomain()?->id(),
    );
    $this->assertSame(
      'loja_aculta_org',
      $inside,
      'Dentro do callback, o Domain ativo é o da loja.',
    );
    $this->assertSame(
      'aculta_org',
      $negotiator->getActiveDomain()?->id(),
      'Após o callback, o Domain anterior é restaurado.',
    );
  }

  /**
   * Tests restoration after a callback throws.
   */
  public function testRunInPurposeRestoresTheStateEvenWhenTheCallbackThrows(): void {
    $this->createDomains();
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    // Negotiate before forcing; a real request does the same. Otherwise the
    // next getActiveDomain() call would replace the forced domain.
    $negotiator->getActiveDomain();
    $context = $negotiator->getContext();
    $context->setDomain(Domain::load('conta_aculta_org'));
    $context->setNegotiated(TRUE);

    try {
      $this->manager->runInSite('main', static function (): void {
        throw new \RuntimeException('falha no callback');
      });
      $this->fail('A exceção do callback deve ser propagada.');
    }
    catch (\RuntimeException) {
      // Esperado: a exceção sai do runInSite().
    }
    $this->assertSame(
      'conta_aculta_org',
      $negotiator->getActiveDomain()?->id(),
      'O Domain anterior volta mesmo com exceção.',
    );
  }

  /**
   * Tests that missing domains prevent the callback from running. */
  public function testRunInPurposeReturnsNullWithoutDomainForPurpose(): void {
    $called = FALSE;
    $result = $this->manager->runInSite('forum', static function () use (&$called) {
      $called = TRUE;
      return 'nao deveria rodar';
    });
    $this->assertNull($result);
    $this->assertFalse($called, 'Sem Domain para o purpose, o callback não executa.');
  }

}
