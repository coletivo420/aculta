<?php

declare(strict_types=1);

namespace Drupal\aculta_domain_config\ParamConverter;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface;
use Drupal\domain_config_entity_ui\ParamConverter\DomainOverrideConfigEntityConverter as BaseDomainOverrideConfigEntityConverter;

/**
 * Lê o override do domínio escolhido no formulário de configuração do bloco.
 *
 * O ParamConverter experimental do domain_config_entity_ui chama load(), que
 * devolve a entidade base. O storage domain-aware só dobra o override em
 * loadOverrideFree(). Este decorator mantém a mesma decisão do contrib, mas
 * usa a leitura que aplica a camada do domínio.
 */
final class BlockDomainOverrideConfigEntityConverter extends BaseDomainOverrideConfigEntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults): mixed {
    $entityTypeId = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return NULL;
    }
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    if (!$entityType instanceof ConfigEntityTypeInterface) {
      return parent::convert($value, $definition, $name, $defaults);
    }
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    if (!$storage instanceof DomainAwareConfigEntityStorageInterface) {
      return parent::convert($value, $definition, $name, $defaults);
    }
    $configName = $entityType->getConfigPrefix() . '.' . $value;
    if ($this->manager->getActiveEditingDomainId()
      && $this->manager->isAllowedConfiguration([$configName])
      && $this->manager->isRegisteredConfiguration([$configName])) {
      // loadOverrideFree() aciona o trait que dobra o override do domínio.
      return $storage->loadOverrideFree($value);
    }
    return parent::convert($value, $definition, $name, $defaults);
  }

}
