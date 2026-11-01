<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;

/**
 * Lista das entidades de configuração do ACULTA Mailer (modelos, layouts e marcas): nome, ID, ações e botão de criar.
 */
final class MailConfigListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'label' => $this->t('Nome'),
      'id' => $this->t('ID'),
      'operations' => $this->t('Operações'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    return [
      'label' => $entity->label(),
      'id' => $entity->id(),
      'operations' => ['data' => $this->buildOperations($entity)],
    ];
  }

  /**
   * Lista com o botão de criar; vazia, com a instrução (não a frase genérica do Core).
   */
  public function render(): array {
    $build = [
      'add' => Link::createFromRoute($this->t('Adicionar'), 'entity.' . $this->entityTypeId . '.add_form')->toRenderable(),
    ];
    $build += parent::render();
    $build['table']['#empty'] = $this->t('Ainda não há itens cadastrados. Use "Adicionar" para criar o primeiro.');
    return $build;
  }

}
