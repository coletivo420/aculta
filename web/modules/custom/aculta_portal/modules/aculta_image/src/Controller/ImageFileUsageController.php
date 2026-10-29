<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adaptado (GPL-2.0-or-later), profile/config/install/views.view.files.yml.
 *
 * Read-only inventory of entities that reference a managed file.
 */
final class ImageFileUsageController extends ControllerBase {

  public function __construct(
    private readonly FileUsageInterface $fileUsage,
  ) {}

  /**
   * Creates the object with its injected services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('file.usage'));
  }

  /**
   * Lists accessible entities recorded by Drupal's file usage service.
   */
  public function view(FileInterface $file): array {
    $rows = [];
    foreach ($this->fileUsage->listUsage($file) as $module => $types) {
      foreach ($types as $type => $ids) {
        foreach ($ids as $id => $count) {
          if (!$this->entityTypeManager()->hasDefinition($type)) {
            continue;
          }
          $entity = $this->entityTypeManager()->getStorage($type)->load($id);
          if ($entity === NULL || !$entity->access('view')) {
            continue;
          }
          $rows[] = [
            $module,
            $type,
            $entity->label() . ' (#' . $entity->id() . ')',
            (string) $count,
          ];
        }
      }
    }

    return [
      '#cache' => ['max-age' => 0],
      'arquivo' => ['#type' => 'item', '#title' => $this->t('Arquivo'), '#plain_text' => $file->label()],
      'usos' => [
        '#type' => 'table',
        '#header' => [$this->t('Módulo'), $this->t('Tipo'), $this->t('Conteúdo'), $this->t('Referências')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum uso registrado para este arquivo.'),
      ],
    ];
  }

}
