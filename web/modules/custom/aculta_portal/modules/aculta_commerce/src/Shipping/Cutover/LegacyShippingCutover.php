<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping\Cutover;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;

/**
 * Cutover do frete do Commerce Shipping (contrib) para o motor próprio do aculta_commerce.
 *
 * Cada etapa é idempotente e não apaga pedidos. Métodos e remessas do contrib só são removidos depois que a
 * migração é conferida, e a desinstalação do contrib só ocorre quando não restam referências a ele.
 */
final class LegacyShippingCutover {

  /**
   * Mapa de plugins do contrib para plugins do motor próprio. */
  public const PLUGIN_MAP = [
    'flat_rate' => 'aculta_flat_rate',
    'flat_rate_per_item' => 'aculta_flat_rate_per_item',
    // Mesmo id no motor. Chaves gravadas na cópia: default_package_type (string) e services (array).
    'aculta_melhorenvio' => 'aculta_melhorenvio',
  ];

  /**
   * Chaves de configuração preservadas por plugin de origem. */
  public const CONFIG_KEYS = [
    'flat_rate' => ['rate_label', 'rate_description', 'rate_amount'],
    'flat_rate_per_item' => ['rate_label', 'rate_description', 'rate_amount'],
    'aculta_melhorenvio' => ['default_package_type', 'services'],
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ModuleInstallerInterface $moduleInstaller,
    private readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    private readonly PluginManagerInterface $widgetManager,
    private readonly PluginManagerInterface $formatterManager,
  ) {}

  /**
   * Remove displays de formulário e de visualização de commerce_shipment que usam plugin inexistente.
   *
   * A entidade commerce_shipment sai com o contrib. O pane AcultaShippingInformation não usa esses displays.
   * Idempotente: displays válidos são mantidos.
   *
   * @return string[]
   *   IDs de configuração removidos (sem outros dados).
   */
  public function removeOrphanShipmentDisplays(): array {
    $removidos = [];
    $fontes = [
      'entity_form_display' => $this->widgetManager,
      'entity_view_display' => $this->formatterManager,
    ];
    foreach ($fontes as $entityTypeId => $manager) {
      $definicoes = $manager->getDefinitions();
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      foreach ($storage->loadByProperties(['targetEntityType' => 'commerce_shipment']) as $display) {
        foreach ($display->getComponents() as $componente) {
          $tipo = $componente['type'] ?? NULL;
          if ($tipo !== NULL && !isset($definicoes[$tipo])) {
            $removidos[] = $display->getConfigDependencyName();
            $display->delete();
            break;
          }
        }
      }
    }
    return $removidos;
  }

  /**
   * Instala as entidades do motor que ainda não existem no banco. Idempotente.
   *
   * @return string[]
   *   IDs instalados nesta chamada.
   */
  public function installEngineEntities(): array {
    $instalados = [];
    foreach (['aculta_shipping_method', 'aculta_shipment', 'aculta_shipment_type'] as $id) {
      if ($this->entityDefinitionUpdateManager->getEntityType($id) !== NULL) {
        continue;
      }
      $this->entityDefinitionUpdateManager->installEntityType(
        $this->entityTypeManager->getDefinition($id)
      );
      $instalados[] = $id;
    }
    return $instalados;
  }

  /**
   * Copia cada método do contrib para o motor próprio, sem apagar o original.
   *
   * @return array{migrados:
   *   int, ja_existentes: int, nao_mapeados: string[]}
   */
  public function migrateMethods(): array {
    $resultado = ['migrados' => 0, 'ja_existentes' => 0, 'nao_mapeados' => []];
    if (!$this->moduleHandler->moduleExists('commerce_shipping')) {
      return $resultado;
    }
    $origem = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $destino = $this->entityTypeManager->getStorage('aculta_shipping_method');

    foreach ($origem->loadMultiple() as $metodo) {
      $plugin = $metodo->get('plugin')->first()?->getValue() ?? [];
      $idLegado = $plugin['target_plugin_id'] ?? '';
      $idNovo = self::PLUGIN_MAP[$idLegado] ?? NULL;
      if ($idNovo === NULL) {
        $resultado['nao_mapeados'][] = $idLegado;
        continue;
      }
      $nome = (string) $metodo->label();
      $existe = $destino->getQuery()
        ->condition('name', $nome)
        ->condition('plugin.target_plugin_id', $idNovo)
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      if ($existe > 0) {
        $resultado['ja_existentes']++;
        continue;
      }

      $configLegada = $plugin['target_plugin_configuration'] ?? [];
      $configNova = array_intersect_key($configLegada, array_flip(self::CONFIG_KEYS[$idLegado] ?? []));
      $novo = $destino->create([
        'name' => $nome,
        'stores' => $metodo->get('stores')->getValue(),
        'plugin' => ['target_plugin_id' => $idNovo, 'target_plugin_configuration' => $configNova],
        'status' => $metodo->isEnabled(),
        'weight' => $metodo->get('weight')->value,
        'conditions' => $metodo->get('conditions')->getValue(),
        'condition_operator' => $metodo->get('condition_operator')->value,
      ]);
      $novo->save();
      $resultado['migrados']++;
    }
    return $resultado;
  }

  /**
   * Campos de remessa copiados do contrib para aculta_shipment. Itens e ajustes não têm equivalente direto e não são copiados.
   */
  public const SHIPMENT_FIELDS = [
    'order_id', 'package_type', 'shipping_service', 'service_label', 'shipping_profile', 'title',
    'weight', 'original_amount', 'amount', 'tracking_code', 'state', 'data', 'shipped',
  ];

  /**
   * Reaponta commerce_order.shipments para aculta_shipment preservando a referência de cada pedido.
   *
   * Cada remessa do contrib é copiada para aculta_shipment; o pedido passa a apontar para a cópia; só então a
   * original é apagada. Depois, storage e campo do pedido recalculam dependências, para que a desinstalação
   * do contrib não os apague. Idempotente.
   *
   * @return array{pedidos_ajustados:
   *   int, remessas_copiadas: int, remessas_apagadas: int, destino: string}
   */
  public function retargetShipments(): array {
    $resultado = [
      'pedidos_ajustados' => 0,
      'remessas_copiadas' => 0,
      'remessas_apagadas' => 0,
      'destino' => 'aculta_shipment',
    ];
    $campo = $this->entityTypeManager->getStorage('field_storage_config')->load('commerce_order.shipments');
    if ($campo === NULL) {
      return $resultado;
    }
    if ($campo->getSetting('target_type') === 'aculta_shipment') {
      $this->atualizarDependenciasDoCampo($campo);
      $this->migrarTiposDePedido();
      return $resultado;
    }

    if ($this->moduleHandler->moduleExists('commerce_shipping')) {
      $legadas = $this->entityTypeManager->getStorage('commerce_shipment');
      $destino = $this->entityTypeManager->getStorage('aculta_shipment');
      $mapa = [];
      $remessas = $legadas->loadMultiple();
      foreach ($remessas as $legada) {
        $nova = $destino->create(['type' => 'default']);
        foreach (self::SHIPMENT_FIELDS as $nome) {
          if ($legada->hasField($nome) && !$legada->get($nome)->isEmpty()) {
            $nova->set($nome, $legada->get($nome)->getValue());
          }
        }
        $nova->set('shipping_method', $this->metodoNovoDe($legada));
        $nova->save();
        $mapa[$legada->id()] = $nova->id();
        $resultado['remessas_copiadas']++;
      }

      $pedidos = $this->entityTypeManager->getStorage('commerce_order');
      $ids = $pedidos->getQuery()->condition('shipments.target_id', NULL, 'IS NOT NULL')->accessCheck(FALSE)->execute();
      foreach ($pedidos->loadMultiple($ids) as $pedido) {
        $novas = [];
        foreach ($pedido->get('shipments') as $item) {
          if (isset($mapa[$item->target_id])) {
            $novas[] = ['target_id' => $mapa[$item->target_id]];
          }
        }
        $pedido->set('shipments', $novas);
        $pedido->save();
        $resultado['pedidos_ajustados']++;
      }

      $legadas->delete($remessas);
      $resultado['remessas_apagadas'] = count($remessas);
    }

    $campo->setSetting('target_type', 'aculta_shipment');
    $this->atualizarDependenciasDoCampo($campo);
    $this->migrarTiposDePedido();
    return $resultado;
  }

  /**
   * Passa o tipo de remessa dos tipos de pedido do contrib para o motor.
   *
   * O campo commerce_order.shipments é anexado ao pedido pelo tipo de pedido: com a configuração do contrib, ele
   * sumiria do pedido depois do uninstall. O motor anexa o campo pela configuração aculta_commerce.shipment_type.
   * A configuração do contrib é retirada: ao salvar, o tipo de pedido deixa de depender do contrib.
   */
  private function migrarTiposDePedido(): void {
    $tipos = $this->entityTypeManager->getStorage('commerce_order_type')->loadMultiple();
    foreach ($tipos as $tipo) {
      $legado = $tipo->getThirdPartySetting('commerce_shipping', 'shipment_type');
      if (empty($legado)) {
        continue;
      }
      $novo = $this->entityTypeManager->getStorage('aculta_shipment_type')->load($legado) ? $legado : 'default';
      if (empty($tipo->getThirdPartySetting('aculta_commerce', 'shipment_type'))) {
        $tipo->setThirdPartySetting('aculta_commerce', 'shipment_type', $novo);
      }
      $tipo->unsetThirdPartySetting('commerce_shipping', 'shipment_type');
      $tipo->save();
    }
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Devolve o método do motor equivalente ao método do contrib de uma remessa, ou NULL se não há.
   */
  private function metodoNovoDe(EntityInterface $remessaLegada): ?int {
    $metodoLegado = $remessaLegada->get('shipping_method')->entity;
    if ($metodoLegado === NULL) {
      return NULL;
    }
    $plugin = $metodoLegado->get('plugin')->first()?->getValue() ?? [];
    $idNovo = self::PLUGIN_MAP[$plugin['target_plugin_id'] ?? ''] ?? NULL;
    if ($idNovo === NULL) {
      return NULL;
    }
    $ids = $this->entityTypeManager->getStorage('aculta_shipping_method')->getQuery()
      ->condition('name', (string) $metodoLegado->label())
      ->condition('plugin.target_plugin_id', $idNovo)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    return $ids ? (int) reset($ids) : NULL;
  }

  /**
   * Recalcula as dependências do storage e dos campos commerce_order.shipments, sem depender do contrib.
   *
   * O salvamento com setSyncing() não recalcula dependências: sem esta etapa, o storage continuava dependendo de
   * commerce_shipping e o uninstall o apagava junto com a tabela do campo.
   */
  private function atualizarDependenciasDoCampo(EntityInterface $storage): void {
    $entidades = [$storage];
    foreach ($this->entityTypeManager->getStorage('field_config')->loadByProperties([
      'entity_type' => 'commerce_order',
      'field_name' => 'shipments',
    ]) as $campoPedido) {
      $entidades[] = $campoPedido;
    }
    foreach ($entidades as $entidade) {
      $entidade->calculateDependencies();
      $entidade->setSyncing(TRUE);
      $entidade->save();
    }
  }

  /**
   * Diz se é seguro desinstalar o contrib: nenhum método, remessa ou referência de campo depende dele.
   *
   * @return string[]
   *   Motivos de bloqueio. Vazio quando a desinstalação é segura.
   */
  public function blockersForUninstall(): array {
    $motivos = [];
    if (!$this->moduleHandler->moduleExists('commerce_shipping')) {
      return $motivos;
    }
    $destino = $this->entityTypeManager->getStorage('aculta_shipping_method');
    $metodos = $this->entityTypeManager->getStorage('commerce_shipping_method')->loadMultiple();
    foreach ($metodos as $metodo) {
      $plugin = $metodo->get('plugin')->first()?->getValue() ?? [];
      $idNovo = self::PLUGIN_MAP[$plugin['target_plugin_id'] ?? ''] ?? NULL;
      if ($idNovo === NULL) {
        $motivos[] = 'método sem mapeamento: ' . $metodo->id();
        continue;
      }
      $copia = $destino->getQuery()
        ->condition('name', (string) $metodo->label())
        ->condition('plugin.target_plugin_id', $idNovo)
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      if ($copia === 0) {
        $motivos[] = 'método sem cópia no motor: ' . $metodo->id();
      }
    }
    if (count($this->entityTypeManager->getStorage('commerce_shipment')->loadMultiple()) > 0) {
      $motivos[] = 'remessas do contrib ainda existem';
    }
    $campo = $this->entityTypeManager->getStorage('field_storage_config')->load('commerce_order.shipments');
    if ($campo !== NULL && $campo->getSetting('target_type') !== 'aculta_shipment') {
      $motivos[] = 'campo commerce_order.shipments ainda aponta para o contrib';
    }
    foreach ($this->entityTypeManager->getStorage('commerce_order_type')->loadMultiple() as $tipo) {
      if (!empty($tipo->getThirdPartySetting('commerce_shipping', 'shipment_type'))) {
        $motivos[] = 'tipo de pedido ainda usa commerce_shipping: ' . $tipo->id();
      }
    }
    return $motivos;
  }

  /**
   * Desinstala o contrib quando não há bloqueios. Retorna TRUE se desinstalou.
   */
  public function uninstallLegacyIfSafe(): bool {
    if (!$this->moduleHandler->moduleExists('commerce_shipping')) {
      return FALSE;
    }
    if ($this->blockersForUninstall() !== []) {
      return FALSE;
    }
    // Métodos do contrib já copiados e conferidos são removidos antes da desinstalação: o módulo não desinstala com entidades.
    $this->entityTypeManager->getStorage('commerce_shipping_method')->delete(
      $this->entityTypeManager->getStorage('commerce_shipping_method')->loadMultiple()
    );
    $this->moduleInstaller->uninstall(['commerce_shipping']);
    return TRUE;
  }

}
