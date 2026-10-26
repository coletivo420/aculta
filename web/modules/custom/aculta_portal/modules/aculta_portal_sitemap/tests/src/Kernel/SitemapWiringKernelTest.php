<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal_sitemap\Kernel;

use Drupal\aculta_portal_sitemap\Plugin\simple_sitemap\UrlGenerator\PurposeNodeUrlGenerator;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Cabeamento do sitemap multidomínio no container com o fechamento completo de dependências do aculta_portal.
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
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_shipping',
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
   * O plugin de URL por purpose é descoberto pelo gerenciador do simple_sitemap.
   */
  public function testPurposeUrlGeneratorPluginIsDiscovered(): void {
    $manager = $this->container->get('plugin.manager.simple_sitemap.url_generator');
    self::assertTrue($manager->hasDefinition('aculta_purpose_node'));
    self::assertSame(PurposeNodeUrlGenerator::class, $manager->getDefinition('aculta_purpose_node')['class']);
  }

  /**
   * O tipo de entidade de sitemap (simple_sitemap_type), onde o tipo aculta_purpose é configurado, está registrado.
   *
   * O próprio tipo aculta_purpose vive no config/sync; este teste só confirma que o tipo de entidade existe.
   */
  public function testSitemapTypeEntityIsRegistered(): void {
    $definition = $this->container->get('entity_type.manager')->getDefinition('simple_sitemap_type');
    self::assertSame('simple_sitemap_type', $definition->id());
  }

}
