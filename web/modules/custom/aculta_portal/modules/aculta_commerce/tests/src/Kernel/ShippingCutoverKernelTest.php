<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
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
   * Módulos do site de teste (mesma base do teste de motor).
   *
   * @var string[]
   */
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
   * Instala o schema do motor e do contrib, como no estado do Runtime antes do cutover.
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
    $this->installEntitySchema('path_alias');
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

  /**
   * Remessa referenciada sobrevive ao uninstall do contrib.
   *
   * Após o retarget e o uninstall, o campo existe, aponta para o motor e o pedido mantém a referência à cópia da
   * remessa em aculta_shipment.
   */
  public function testRemessaReferenciadaSobreviveAoUninstall(): void {
    // A desinstalação do contrib consulta comentários e a visão de remessas: os schemas precisam existir aqui.
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('flagging');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['comment', 'language']);
    // Estado do Runtime: o tipo de pedido liga o campo pela configuração do contrib.
    \Drupal::entityTypeManager()->getStorage('commerce_order_type')->load('default')
      ->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default')
      ->save();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $dados = $this->criarLegado();
    $pedido = $dados['pedido'];
    // Uma remessa descartada antes desloca os ids: a referenciada não tem o mesmo id da cópia, como no Runtime.
    $descartada = \Drupal::entityTypeManager()->getStorage('commerce_shipment')->create([
      'type' => 'default',
      'order_id' => $pedido->id(),
      'title' => 'Descartada',
    ]);
    $descartada->save();
    $descartada->delete();
    $legada = \Drupal::entityTypeManager()->getStorage('commerce_shipment')->create([
      'type' => 'default',
      'order_id' => $pedido->id(),
      'shipping_method' => $dados['metodo']->id(),
      'title' => 'Remessa teste',
      'shipping_service' => 'default',
      'amount' => ['number' => '12.50', 'currency_code' => 'BRL'],
    ]);
    $legada->save();
    $pedido->set('shipments', [$legada]);
    $pedido->save();

    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');
    $service->installEngineEntities();
    $service->migrateMethods();
    $resultado = $service->retargetShipments();
    $this->assertSame(1, $resultado['remessas_copiadas']);
    $this->assertSame(1, $resultado['referencias_reapontadas']);

    $pedido = Order::load($pedido->id());
    $referencias = $pedido->get('shipments')->referencedEntities();
    $this->assertCount(1, $referencias, 'o pedido mantém a referência à remessa');
    $copia = reset($referencias);
    $this->assertNotSame($legada->id(), $copia->id(), 'a cópia não pode coincidir com o id legado');
    $this->assertSame('aculta_shipment', $copia->getEntityTypeId());
    $this->assertSame('Remessa teste', $copia->get('title')->value);
    $this->assertSame([], $service->blockersForUninstall());

    $this->assertTrue($service->uninstallLegacyIfSafe(), 'desinstalação liberada sem bloqueios');
    $this->assertFalse($this->container->get('module_handler')->moduleExists('commerce_shipping'));

    $campo = \Drupal::entityTypeManager()->getStorage('field_storage_config')->load('commerce_order.shipments');
    $this->assertNotNull($campo, 'o storage do campo sobrevive ao uninstall');
    $this->assertSame('aculta_shipment', $campo->getSetting('target_type'));
    $this->assertNotContains('commerce_shipping', $campo->getDependencies()['module'] ?? []);
    $definicao = \Drupal::service('entity_field.manager')->getFieldDefinitions('commerce_order', 'default')['shipments'] ?? NULL;
    $this->assertNotNull($definicao, 'o campo continua anexado ao tipo de pedido, agora pelo motor');
    $this->assertSame('aculta_shipment', $definicao->getSetting('target_type'));

    $pedido = Order::load($pedido->id());
    $referencias = $pedido->get('shipments')->referencedEntities();
    $this->assertCount(1, $referencias, 'a referência do pedido é preservada após o uninstall');
    $this->assertSame($copia->id(), reset($referencias)->id());
  }

  /**
   * Método legado do Melhor Envio (plugin aculta_melhorenvio) migra com default_package_type e services.
   */
  public function testMigratesLegacyMelhorEnvioMethodKeepingItsConfig(): void {
    $loja = Store::create([
      'type' => 'online',
      'name' => 'Loja ME',
      'uid' => 1,
      'default_currency' => 'BRL',
      'mail' => 'me@example.test',
      'address' => ['country_code' => 'BR'],
    ]);
    $loja->save();
    $legado = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method')->create([
      'name' => 'Melhor Envio',
      'stores' => [$loja->id()],
      'plugin' => [
        'target_plugin_id' => 'aculta_melhorenvio',
        'target_plugin_configuration' => [
          'default_package_type' => 'custom_box',
          'services' => ['1', '2'],
        ],
      ],
      'status' => TRUE,
      'weight' => 1,
    ]);
    $legado->save();
    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');
    $service->installEngineEntities();

    $r = $service->migrateMethods();

    $this->assertSame(1, $r['migrados']);
    $this->assertSame([], $r['nao_mapeados']);
    $novo = \Drupal::entityTypeManager()->getStorage('aculta_shipping_method')->loadByProperties(['name' => 'Melhor Envio']);
    $this->assertCount(1, $novo);
    $novo = reset($novo);
    $this->assertSame('aculta_melhorenvio', $novo->get('plugin')->target_plugin_id);
    $config = $novo->get('plugin')->target_plugin_configuration;
    $this->assertSame('custom_box', $config['default_package_type']);
    $this->assertSame(['1', '2'], $config['services']);
    $this->assertArrayNotHasKey('rate_label', $config, 'chaves de tarifa fixa não são copiadas para o Melhor Envio');
  }

  /**
   * Display de commerce_shipment com widget inexistente é removido; display válido permanece.
   */
  public function testRemovesOnlyOrphanShipmentDisplays(): void {
    // O módulo flag consulta a tabela de flagging ao excluir um display: instala o schema no teste.
    $this->installEntitySchema('flagging');
    // Display órfão gravado como configuração bruta, como ocorre na cópia: salvar via entidade falha ao calcular
    // dependências de um widget inexistente.
    \Drupal::configFactory()->getEditable('core.entity_form_display.commerce_shipment.default.checkout')
      ->setData([
        'uuid' => 'a1b2c3d4-0000-4000-8000-000000000001',
        'langcode' => 'en',
        'status' => TRUE,
        'dependencies' => [],
        'id' => 'commerce_shipment.default.checkout',
        'targetEntityType' => 'commerce_shipment',
        'bundle' => 'default',
        'mode' => 'checkout',
        'content' => [
          'shipping_rate' => [
            'type' => 'aculta_shipping_rate',
            'weight' => 1,
            'region' => 'content',
            'settings' => [],
            'third_party_settings' => [],
          ],
        ],
        'hidden' => [],
      ])->save();
    $valido = EntityFormDisplay::load('commerce_shipment.default.default')
      ?? EntityFormDisplay::create([
        'targetEntityType' => 'commerce_shipment',
        'bundle' => 'default',
        'mode' => 'default',
      ]);
    $valido->set('content', [
      'title' => [
        'type' => 'string_textfield',
        'weight' => 1,
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);
    $valido->set('status', TRUE);
    $valido->save();
    $service = \Drupal::service('aculta_commerce.legacy_shipping_cutover');

    $removidos = $service->removeOrphanShipmentDisplays();

    $this->assertContains('core.entity_form_display.commerce_shipment.default.checkout', $removidos);
    $this->assertNotContains('core.entity_form_display.commerce_shipment.default.default', $removidos);
    $this->assertNull(EntityFormDisplay::load('commerce_shipment.default.checkout'));
    $this->assertNotNull(EntityFormDisplay::load('commerce_shipment.default.default'));
    $this->assertSame([], $service->removeOrphanShipmentDisplays(), 'segunda execução é idempotente');
  }

}
