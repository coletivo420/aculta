<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\aculta_patcher\PatchManifestImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administration table for Composer patch records. */
final class AcultaPatchListBuilder extends ConfigEntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected PatchManifestImporter $importer,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('aculta_patcher.importer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $table = $build['table'];
    $build['table'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'patcher-table-wrapper'],
      'table' => $table,
    ];
    $build['#attached']['library'][] = 'aculta_patcher/admin';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    // Discover new Composer manifest entries before building the collection.
    // Existing records are never overwritten when they have been edited here.
    $this->importer->importMissing();
    return parent::load();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = parent::buildHeader();
    $operations = $header['operations'];
    unset($header['operations']);
    $header = ['label' => $this->t('Patch/snippet')] + $header;
    $header['package'] = $this->t('Pacote Composer');
    $header['version'] = $this->t('Versão');
    $header['status'] = $this->t('Estado no próximo build');
    $header['operations'] = $operations;
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof AcultaPatch);
    $row = parent::buildRow($entity);
    $operations = $row['operations'];
    unset($row['operations']);
    $row = ['label' => $entity->label()] + $row;
    $row['package'] = $entity->getPackage();
    $row['version'] = $entity->getVersion();
    $row['status'] = $entity->status() ? $this->t('Ativo') : $this->t('Desativado');
    $row['operations'] = $operations;
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $args = func_get_args();
    $operations = parent::getDefaultOperations(...$args);
    assert($entity instanceof AcultaPatch);

    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Editar');
    }
    if ($entity->status()) {
      $operations['disable'] = [
        'title' => $this->t('Desabilitar'),
        'weight' => 40,
        'url' => $this->ensureDestination($entity->toUrl('disable-form')),
      ];
    }
    else {
      $operations['enable'] = [
        'title' => $this->t('Habilitar'),
        'weight' => 40,
        'url' => $this->ensureDestination($entity->toUrl('enable-form')),
      ];
    }
    return $operations;
  }

}
