<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Presentation\DomainPresentationBuilder;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the Portal supplies the Domain Header contract for every purpose.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class DomainPresentationBuilderKernelTest extends KernelTestBase {

  /**
   * Canonical domains resolved by DomainPurposeManager.
   *
   * @var array<string, string>
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
    'links' => 'links_aculta_org',
    'eventos' => 'eventos_aculta_org',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'domain'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
  }

  /**
   * Tests that every purpose receives a Domain Header and its own home URL.
   */
  public function testEveryPurposeHasDomainHeaderAndResolvedHomeUrl(): void {
    foreach (self::DOMAINS as $purpose => $domainId) {
      Domain::create([
        'id' => $domainId,
        'hostname' => $purpose . '.example.invalid',
        'name' => $purpose,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }

    $purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
    $pathMatcher = $this->createMock(PathMatcherInterface::class);
    $pathMatcher->method('isFrontPage')->willReturn(FALSE);
    $builder = new DomainPresentationBuilder(
      $purposeManager,
      $this->container->get('string_translation'),
      NULL,
      $pathMatcher,
    );

    foreach (self::DOMAINS as $purpose => $domainId) {
      $presentation = $builder->build($purpose);
      $this->assertNotNull($presentation, "O purpose $purpose deve ter apresentação.");
      $data = $presentation->toThemeArray();
      $this->assertTrue($data['layout']['show_site_header'], "O purpose $purpose deve mostrar Domain Header.");
      $this->assertStringStartsWith(
        'https://' . $purpose . '.example.invalid/',
        $data['identity']['home_url'],
        "A URL da home de $purpose deve permanecer vinculada ao Domain configurado.",
      );
      $this->assertContains(
        'config:domain.record.' . $domainId,
        $presentation->getCacheTags(),
        "A apresentação de $purpose deve variar com a configuração de seu Domain.",
      );
      $this->assertSame($purpose, $data['identity']['purpose']);
      $this->assertSame($domainId, self::DOMAINS[$data['identity']['purpose']]);
    }

    $cacheContexts = $presentation->getCacheContexts();
    foreach (['domain', 'languages:language_interface', 'url.site', 'url.path.is_front'] as $context) {
      $this->assertContains($context, $cacheContexts);
    }

    $links = $builder->build('links')?->toThemeArray();
    $this->assertNotNull($links);
    $this->assertTrue($links['layout']['links_page']);
    $this->assertTrue($links['layout']['show_site_header']);
  }

  /**
   * Unknown purposes do not receive a presentation contract.
   */
  public function testUnknownPurposeHasNoPresentation(): void {
    $purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
    $builder = new DomainPresentationBuilder(
      $purposeManager,
      $this->container->get('string_translation'),
      NULL,
      $this->createMock(PathMatcherInterface::class),
    );

    $this->assertNull($builder->build('unknown'));
  }

}
