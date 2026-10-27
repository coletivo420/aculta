<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping\Cutover;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
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
  ) {}

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
   * Remove do pedido as referências a remessas do contrib e reaponta o campo para o motor próprio.
   *
   * Os pedidos são preservados (só a referência de remessa é removida). Remessas legadas são apagadas.
   *
   * @return array{pedidos_ajustados:
   *   int, remessas_apagadas: int, destino: string}
   */
  public function retargetShipments(): array {
    $resultado = ['pedidos_ajustados' => 0, 'remessas_apagadas' => 0, 'destino' => 'aculta_shipment'];
    $campo = $this->entityTypeManager->getStorage('field_storage_config')->load('commerce_order.shipments');
    if ($campo === NULL) {
      return $resultado;
    }
    $alvoAtual = $campo->getSetting('target_type');
    if ($alvoAtual === 'aculta_shipment') {
      return $resultado;
    }

    if ($this->moduleHandler->moduleExists('commerce_shipping')) {
      $pedidos = $this->entityTypeManager->getStorage('commerce_order');
      $ids = $pedidos->getQuery()->condition('shipments.target_id', NULL, 'IS NOT NULL')->accessCheck(FALSE)->execute();
      foreach ($pedidos->loadMultiple($ids) as $pedido) {
        $pedido->set('shipments', []);
        $pedido->save();
        $resultado['pedidos_ajustados']++;
      }
      $legadas = $this->entityTypeManager->getStorage('commerce_shipment');
      $remessas = $legadas->loadMultiple();
      $legadas->delete($remessas);
      $resultado['remessas_apagadas'] = count($remessas);
    }

    $campo->setSetting('target_type', 'aculta_shipment');
    $campo->setSyncing(TRUE);
    $campo->save();
    return $resultado;
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
