<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_secrets_manager\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies panel routes and permissions with the submodule installed.
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_secrets_manager')]
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class SecretsRoutesKernelTest extends KernelTestBase {

  /**
   * Modules enabled for the kernel test.
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'field',
    'image',
    'media',
    'views',
    'node',
    'comment',
    'filter',
    'key',
    'token',
    'path_alias',
    'taxonomy',
    'datetime',
    'options',
    'path',
    'metatag',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'ctools',
    'ctools_views',
    'profile',
    'vvjs',
    'address',
    'social_api',
    'flexible_permissions',
    'aculta_image',
    'aculta_mailer',
    'aculta_user',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'commerce_price',
    'commerce_product',
    'commerce_store',
    'commerce_order',
    'commerce_payment',
    'commerce_shipping',
    'commerce_cart',
    'physical',
    'text', 'language',
    'locale',
    'aculta_portal',
    'aculta_secrets_manager',
    'aculta_portal_cep',
    'login_emailusername',
    'social_auth',
    'group',
    'lms',
    'commerce',
    'aculta_pagamentos_mercadopago',
    'schema_metatag',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_organization',
    'schema_product',
    'schema_web_site',
    'schema_web_page',
  ];

  /**
   * Tests route permission requirements and CSRF protection.
   */
  public function testPanelRoutesRequirePermissionAndCsrfForReveal(): void {
    $provider = $this->container->get('router.route_provider');
    $show = $provider->getRouteByName('aculta_portal.secrets_show');
    $this->assertSame('administer aculta secrets', $show->getRequirement('_permission'));
    $this->assertSame('TRUE', $show->getRequirement('_csrf_token'));
    $this->assertSame('aculta_secrets_manager.controller:show', $show->getDefault('_controller'));
    $this->assertSame('/painel-administrativo/aculta/segredos', $provider->getRouteByName('aculta_portal.secrets_import')->getPath());
  }

}
