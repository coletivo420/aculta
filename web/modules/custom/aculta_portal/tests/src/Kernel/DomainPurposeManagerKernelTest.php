<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Domínio por purpose (S2 / DT-P03): mapa canônico, resolução e restauração do Domain ativo.
 *
 * A restauração importa: runInPurpose() troca o Domain ativo para avaliar acesso em outro
 * contexto. Se o domínio anterior não voltar, a negociação do restante da requisição fica errada.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class DomainPurposeManagerKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'domain'];

  /** Purpose → Domain ID canônico (espelha DomainPurposeManager::DOMAIN_IDS). */
  private const DOMAINS = [
    'main' => 'aculta_org',
    'account' => 'conta_aculta_org',
    'support' => 'apoio_aculta_org',
    'magazine' => 'coletivo420_aculta_org',
    'wiki' => 'wiki420_aculta_org',
    'shop' => 'loja_aculta_org',
    'courses' => 'cursos_aculta_org',
    'forum' => 'forum_aculta_org',
  ];

  private DomainPurposeManager $manager;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->manager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
  }

  /** Cria os Domains canônicos com hostname de teste. */
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

  public function testEveryCanonicalDomainIdMapsToItsPurpose(): void {
    foreach (self::DOMAINS as $purpose => $id) {
      $this->assertSame($purpose, $this->manager->getPurposeForDomainId($id), "Domain $id deve mapear para $purpose.");
    }
  }

  public function testUnknownDomainIdHasNoPurpose(): void {
    $this->assertNull($this->manager->getPurposeForDomainId('desconhecido_aculta_org'));
    $this->assertNull($this->manager->getPurposeForDomainId(''));
  }

  public function testGetDomainReturnsTheEntityForAPurpose(): void {
    $this->createDomains();
    $domain = $this->manager->getDomain('shop');
    $this->assertNotNull($domain);
    $this->assertSame('loja_aculta_org', $domain->id());
  }

  public function testGetDomainReturnsNullForUnknownPurposeOrMissingEntity(): void {
    $this->assertNull($this->manager->getDomain('inexistente'));
    // Purpose válido, mas o Domain ainda não existe no banco.
    $this->assertNull($this->manager->getDomain('forum'));
  }

  public function testRunInPurposeSwitchesToThePurposeDomainAndRestoresThePrevious(): void {
    $this->createDomains();
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain(Domain::load('aculta_org'));

    $inside = $this->manager->runInPurpose('shop', static fn() => $negotiator->getActiveDomain()?->id());
    $this->assertSame('loja_aculta_org', $inside, 'Dentro do callback, o Domain ativo é o da loja.');
    $this->assertSame('aculta_org', $negotiator->getActiveDomain()?->id(), 'Após o callback, o Domain anterior é restaurado.');
  }

  public function testRunInPurposeRestoresTheStateEvenWhenTheCallbackThrows(): void {
    $this->createDomains();
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    // Negocia antes de forçar, como uma requisição real: senão getActiveDomain() renegocia e descarta o domínio forçado.
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain(Domain::load('conta_aculta_org'));

    try {
      $this->manager->runInPurpose('main', static function (): void {
        throw new \RuntimeException('falha no callback');
      });
      $this->fail('A exceção do callback deve ser propagada.');
    }
    catch (\RuntimeException) {
      // Esperado: a exceção sai do runInPurpose().
    }
    $this->assertSame('conta_aculta_org', $negotiator->getActiveDomain()?->id(), 'O Domain anterior volta mesmo com exceção.');
  }

  public function testRunInPurposeReturnsNullWithoutADomainForThePurpose(): void {
    $called = FALSE;
    $result = $this->manager->runInPurpose('forum', static function () use (&$called) {
      $called = TRUE;
      return 'nao deveria rodar';
    });
    $this->assertNull($result);
    $this->assertFalse($called, 'Sem Domain para o purpose, o callback não executa.');
  }

}
