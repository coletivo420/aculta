<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal_sitemap\Kernel;

use Drupal\aculta_portal_sitemap\Plugin\simple_sitemap\UrlGenerator\PurposeNodeUrlGenerator;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain_config\Config\DomainConfigCollectionUtils;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Checks sitemap plugin wiring and Portal dependency closure.
 */
#[Group('aculta_portal_sitemap')]
#[RunTestsInSeparateProcesses]
final class SitemapWiringKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'aculta_commerce_melhorenvio',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_pagamentos_mercadopago',
    'aculta_portal',
    'aculta_user',
    'config',
    'aculta_portal_cep',
    'aculta_portal_sitemap',
    'address',
    'block',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_store',
    'ctools',
    'ctools_views',
    'datetime',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'entity',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flexible_permissions',
    'group',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'metatag',
    'node',
    'options',
    'path',
    'path_alias',
    'physical',
    'profile',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'simple_sitemap',
    'social_api',
    'social_auth',
    'state_machine',
    'taxonomy',
    'token',
    'user',
    'views',
    'vvj_core',
    'vvjs',
  ];

  /**
   * Confirms discovery of the purpose URL generator plugin.
   */
  public function testPurposeUrlGeneratorPluginIsDiscovered(): void {
    $manager = $this->container->get('plugin.manager.simple_sitemap.url_generator');
    self::assertTrue($manager->hasDefinition('aculta_purpose_node'));
    self::assertSame(PurposeNodeUrlGenerator::class, $manager->getDefinition('aculta_purpose_node')['class']);
  }

  /**
   * Confirms the sitemap entity type used by aculta_purpose is registered.
   *
   * The sitemap type itself lives in config/sync; this checks its entity type.
   */
  public function testSitemapTypeEntityIsRegistered(): void {
    $definition = $this->container->get('entity_type.manager')->getDefinition('simple_sitemap_type');
    self::assertSame('simple_sitemap_type', $definition->id());
  }

  /**
   * Confirms front-page aliases are resolved in the sitemap purpose context.
   */
  public function testPurposeFrontAliasUsesAndRestoresThePurposeDomain(): void {
    $this->installEntitySchema('domain');
    $main = Domain::create([
      'id' => 'aculta_org',
      'hostname' => 'main.example.invalid',
      'name' => 'main',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ]);
    $main->save();
    $wiki = Domain::create([
      'id' => 'wiki420_aculta_org',
      'hostname' => 'wiki.example.invalid',
      'name' => 'wiki',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ]);
    $wiki->save();

    $storage = $this->container->get('config.storage');
    $collection = $storage->createCollection(
      DomainConfigCollectionUtils::createDomainConfigCollectionName($wiki->id()),
    );
    $collection->write('system.site', ['page' => ['front' => '/wiki/inicio']]);

    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($main);
    $aliasManager = $this->createMock(AliasManagerInterface::class);
    $aliasManager->expects(self::once())
      ->method('getAliasByPath')
      ->with('/node/7')
      ->willReturnCallback(static function () use ($negotiator): string {
        self::assertSame('wiki420_aculta_org', $negotiator->getActiveDomain()?->id());
        return '/wiki/inicio';
      });

    $generator = new PurposeNodeUrlGenerator(
      [],
      'aculta_purpose_node',
      [],
      $this->container->get('simple_sitemap.logger'),
      $this->container->get('simple_sitemap.settings'),
      $this->container->get('aculta_portal.domain_purpose'),
      $this->container->get('entity_type.manager'),
      $this->container->get('account_switcher'),
      $storage,
      $aliasManager,
    );
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(7);
    $method = new \ReflectionMethod(PurposeNodeUrlGenerator::class, 'isPurposeFrontNode');

    self::assertTrue($method->invoke($generator, $node, 'wiki'));
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
  }

  /**
   * Confirms the Wiki listing is emitted as a public path dataset.
   */
  public function testWikiListingIsIncludedInThePurposeDatasets(): void {
    $this->installEntitySchema('domain');
    Domain::create([
      'id' => 'wiki420_aculta_org',
      'hostname' => 'wiki.example.invalid',
      'name' => 'wiki',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ])->save();

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);
    $entityTypeManager->method('hasDefinition')->with('aculta_event')->willReturn(FALSE);

    $sitemap = $this->createMock(SimpleSitemapInterface::class);
    $sitemap->method('getThirdPartySetting')
      ->with('aculta_portal_sitemap', PurposeNodeUrlGenerator::PURPOSE_KEY)
      ->willReturn('wiki');
    $projectRoot = sys_get_temp_dir() . '/aculta-sitemap-kernel-' . getmypid() . '-' . bin2hex(random_bytes(4));
    $deployerConfig = $projectRoot . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json';
    mkdir(dirname($deployerConfig), 0777, TRUE);
    self::assertTrue(copy(DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json', $deployerConfig));
    $environmentFile = $projectRoot . '/var/deployer/environment.json';
    mkdir(dirname($environmentFile), 0777, TRUE);
    file_put_contents($environmentFile, json_encode([
      'schema' => 1,
      'environment' => 'test',
      'site' => 'https://site.example.invalid',
    ], JSON_THROW_ON_ERROR));
    $purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
      $projectRoot . '/web',
    );
    $generator = new PurposeNodeUrlGenerator(
      [],
      'aculta_purpose_node',
      [],
      $this->container->get('simple_sitemap.logger'),
      $this->container->get('simple_sitemap.settings'),
      $purposeManager,
      $entityTypeManager,
      $this->container->get('account_switcher'),
      $this->container->get('config.storage'),
      $this->container->get('path_alias.manager'),
    );
    $generator->setSitemap($sitemap);

    try {
      self::assertContains(['purpose' => 'wiki', 'path' => '/wiki/verbetes'], $generator->getDataSets());
      $generated = $generator->generate(['purpose' => 'wiki', 'path' => '/wiki/verbetes']);
      $expectedUrl = $purposeManager->environmentPathUrl('wiki', '/wiki/verbetes');
      self::assertNotNull($expectedUrl);
      self::assertSame($expectedUrl->toString(), $generated[0]['url']);
    }
    finally {
      (new Filesystem())->remove($projectRoot);
    }
  }

}
