<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/** Administration table for Composer patch records. */
final class AcultaPatchListBuilder extends ConfigEntityListBuilder {

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

}
