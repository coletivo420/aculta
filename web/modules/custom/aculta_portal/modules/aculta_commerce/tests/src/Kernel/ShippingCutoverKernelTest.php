<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_store\Entity\Store;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cutover do frete: instalação do motor, migração de FlatRate, idempotência e pedidos preservados.
 *
 * Parte de um site com commerce_shipping e um FlatRate configurado, sem as tabelas do motor próprio
 * (estado do Runtime antes do cutover).
 */
#[Group('aculta_commerce')]
class ShippingCutoverKernelTest extends KernelTestBase {

  /**
   * @var string[] */
  protected static $modules = [
    'aculta_commerce',
    'aculta_document_viewer',
    'aculta_forum',
    'aculta_gallery',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_messenger',
    'aculta_pagamentos_mercadopago',
    'aculta_pdf_viewer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_studio',
    'aculta_user',
    'address',
    'block',
    'ckeditor5',
    'comment',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_product',
    'commerce_store',
    'content_moderation',
    'csp',
    'ctools',
    'ctools_views',
    'datetime',
    'datetime_range',
    'diff',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'editor',
    'embed',
    'entity',
    'entity_embed',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flag',
    'flexible_permissions',
    'group',
    'history',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'link',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'media_gallery',
    'media_library',
    'menu_link_content',
    'menu_ui',
    'message',
    'message_notify',
    'message_subscribe',
    'metatag',
    'migrate',
    'node',
    'options',
    'path',
    'path_alias',
    'pathauto',
    'photoswipe',
    'physical',
    'private_message',
    'profile',
    'reporting',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'search_api',
    'search_api_db',
    'site_alert',
    'social_api',
    'social_auth',
    'state_machine',
    'system',
    'taxonomy',
    'text',
    'token',
    'user',
    'views',
    'views_bulk_operations',
    'vvj_core',
    'vvjs',
    'workflows',
    'commerce_shipping',
  ];

  /**
   *
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_shipping_method');
    $this->installEntitySchema('commerce_shipment');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['commerce_order', 'commerce_store', 'commerce_shipping']);
  }

  /**
   * Cria um FlatRate do contrib e um pedido, como existiam antes do cutover.
   */
  private function criarLegado(): array {
    $loja = Store::create([
      'type' => 'online',
      'name' => 'Loja teste',
      'uid' => 1,
      'default_currency' => 'BRL',
      'mail' => 'loja@example.test',
      'address' => ['country_code' => 'BR'],
    ]);
    $loja->save();
    $metodo = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method')->create([
      'name' => 'Tarifa fixa',
      'stores' => [$loja->id()],
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Entrega padrão',
          'rate_description' => 'Envio fixo',
          'rate_amount' => ['number' => '12.50', 'currency_code' => 'BRL'],
          'services' => ['default'],
        ],
      ],
      'status' => TRUE,
      'weight' => 3,
    ]);
    $metodo->save();
    $pedido = Order::create([
      'type' => 'default',
      'store_id' => $loja->id(),
      'mail' => 'cliente@example.test',
      'uid' => 0,
      'state' => 'draft',
    ]);
    $pedido->save();
    return ['loja' => $loja, 'metodo' => $metodo, 'pedido' => $pedido];
  }

  /**
   * O cutover instala as entidades do motor e migra o FlatRate com valores, lojas e status preservados.
   */
  public function testMigratesFlatRatePreservingValuesAndStores(): void {
    $dados = $this->criarLegado();
    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');

    $instaladas = $service->installEngineEntities();
    $this->assertContains('aculta_shipping_method', $instaladas);

    $r = $service->migrateMethods();
    $this->assertSame(1, $r['migrados']);
    $this->assertSame([], $r['nao_mapeados']);

    $novo = \Drupal::entityTypeManager()->getStorage('aculta_shipping_method')->loadByProperties(['name' => 'Tarifa fixa']);
    $this->assertCount(1, $novo);
    $novo = reset($novo);
    $this->assertSame('aculta_flat_rate', $novo->get('plugin')->target_plugin_id);
    $config = $novo->get('plugin')->target_plugin_configuration;
    $this->assertSame('Entrega padrão', $config['rate_label']);
    $this->assertSame('12.50', $config['rate_amount']['number']);
    $this->assertArrayNotHasKey('services', $config, 'services do contrib não tem equivalente e não é copiado');
    $this->assertTrue($novo->isEnabled());
    $this->assertSame(3, (int) $novo->get('weight')->value);
    $this->assertSame($dados['loja']->id(), $novo->get('stores')->target_id);

    // O original continua existindo: a remoção só ocorre depois da conferência.
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('commerce_shipping_method')->load($dados['metodo']->id()));
  }

  /**
   * Repetir a migração não duplica métodos.
   */
  public function testMigrationIsIdempotent(): void {
    $this->criarLegado();
    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');
    $service->installEngineEntities();
    $service->migrateMethods();
    $segunda = $service->migrateMethods();
    $this->assertSame(0, $segunda['migrados']);
    $this->assertSame(1, $segunda['ja_existentes']);
    $this->assertSame(1, (int) \Drupal::entityQuery('aculta_shipping_method')->accessCheck(FALSE)->count()->execute());
    $this->assertSame([], $service->installEngineEntities(), 'instalar de novo não faz nada');
  }

  /**
   * Os pedidos existentes não são apagados pela migração.
   */
  public function testOrdersArePreserved(): void {
    $dados = $this->criarLegado();
    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');
    $service->installEngineEntities();
    $service->migrateMethods();
    $this->assertNotNull(Order::load($dados['pedido']->id()), 'pedido permanece após migração de métodos');
  }

  /**
   * A desinstalação do contrib fica bloqueada sem cópia no motor e é liberada depois da migração.
   */
  public function testUninstallBlockedUntilCopiesExist(): void {
    $this->criarLegado();
    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');
    $service->installEngineEntities();

    $antes = $service->blockersForUninstall();
    $this->assertNotEmpty($antes);
    $this->assertStringContainsString('sem cópia no motor', implode(' ', $antes));
    // Com bloqueios, o uninstall é recusado antes de qualquer remoção.
    $this->assertFalse($service->uninstallLegacyIfSafe(), 'não desinstala com bloqueios');
    $this->assertTrue($this->container->get('module_handler')->moduleExists('commerce_shipping'));

    $service->migrateMethods();
    // O campo commerce_order.shipments ainda aponta para o contrib: o bloqueio tem que persistir até o reapontamento.
    $this->assertStringContainsString('campo commerce_order.shipments', implode(' ', $service->blockersForUninstall()));
    $service->retargetShipments();
    $this->assertSame([], $service->blockersForUninstall());
    // A desinstalação em si (remoção de métodos e uninstall) não é provada aqui: exige o schema completo de
    // comment, file e entity_usage. Fica para o ensaio no banco restaurado do snapshot.
    $this->assertTrue($this->container->get('module_handler')->moduleExists('commerce_shipping'));
  }

}
