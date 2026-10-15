<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Badge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** Catálogo configurável de critérios fechados; User preserva definições e fatos. */
final class AchievementDefinitions {

  public const TRIGGERS = [
    'manual' => 'Concessão administrativa',
    'order_completed' => 'Pedido concluído',
    'donation_completed' => 'Apoio concluído no sistema de doações',
    'forum_comment' => 'Resposta publicada no Growroom420',
    'wiki_revision' => 'Revisão publicada na Wiki420',
    'course_passed' => 'Curso concluído com aprovação',
    'profile_basic' => 'Perfil básico completo',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configs,
    private readonly LockBackendInterface $lock,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  public function all(): array {
    $defaults = [];
    $triggers = [
      BadgeCatalog::FUNDADORE => ['manual'],
      BadgeCatalog::ASSOCIADO => ['manual'],
      BadgeCatalog::APOIADOR => ['order_completed', 'donation_completed', 'manual'],
      BadgeCatalog::PRIMEIRO_COMENTARIO => ['forum_comment'],
      BadgeCatalog::PRIMEIRA_EDICAO_WIKI => ['wiki_revision'],
      BadgeCatalog::PRIMEIRO_CURSO => ['course_passed'],
      BadgeCatalog::PERFIL_BASICO => ['profile_basic'],
    ];
    $legacy = [
      BadgeCatalog::FUNDADORE => ['Reconhece quem participou da criação da Associação Cultural Antiproibicionista e da construção inicial do Coletivo 420.', 'fundadore'],
      BadgeCatalog::ASSOCIADO => ['Reconhecimento da participação na categoria Membro Antiproibicionista do Coletivo 420.', 'associado'],
      BadgeCatalog::APOIADOR => ['Reconhece o apoio e a participação nas atividades e nos ideais do Coletivo 420.', 'apoiador'],
    ];
    foreach (BadgeCatalog::all() as $id => $definition) {
      $defaults[$id] = $definition + ['description' => $legacy[$id][0] ?? '', 'enabled' => TRUE, 'triggers' => $triggers[$id], 'criterion_version' => 1, 'image' => $legacy[$id][1] ?? '', 'image_uuid' => ''];
    }
    foreach ($this->configs->get('aculta_user.achievement_definitions')->get('definitions') ?? [] as $id => $definition) {
      if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', (string) $id) || !is_array($definition) || empty($definition['label'])) {
        continue;
      }
      $definition['triggers'] = array_values(array_intersect(array_keys(self::TRIGGERS), $definition['triggers'] ?? []));
      $definition['origem'] = $definition['triggers'] === ['manual'] ? BadgeCatalog::MANUAL : BadgeCatalog::AUTOMATICA;
      $defaults[$id] = $definition;
    }
    return $defaults;
  }

  /** Salva um item sem sobrescrever edições concorrentes dos demais. */
  public function save(string $id, array $definition): void {
    if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $id) || trim((string) ($definition['label'] ?? '')) === '') {
      throw new \InvalidArgumentException('Identificador ou nome de conquista inválido.');
    }
    $triggers = array_values(array_intersect(array_keys(self::TRIGGERS), $definition['triggers'] ?? []));
    if ($triggers === []) {
      throw new \InvalidArgumentException('Escolha pelo menos uma forma de concessão.');
    }
    $name = 'aculta_user:achievement_definitions';
    if (!$this->lock->acquire($name)) {
      throw new \RuntimeException('Catálogo ocupado; tente novamente.');
    }
    try {
      $this->configs->reset('aculta_user.achievement_definitions');
      $previous = $this->all()[$id] ?? NULL;
      $imageUuid = (string) ($previous['image_uuid'] ?? '');
      $oldFiles = !empty($previous['image_uuid']) ? $this->entities->getStorage('file')->loadByProperties(['uuid' => $previous['image_uuid']]) : [];
      $oldFile = $oldFiles ? reset($oldFiles) : NULL;
      $newFile = NULL;
      if (!empty($definition['remove_image'])) {
        $imageUuid = '';
      }
      if (!empty($definition['image_fid'])) {
        $file = $this->entities->getStorage('file')->load((int) $definition['image_fid']);
        if (!$file || !str_starts_with($file->getFileUri(), 'public://') || !str_starts_with((string) $file->getMimeType(), 'image/')) {
          throw new \InvalidArgumentException('Envie uma imagem pública válida.');
        }
        $file->setPermanent();
        $file->save();
        $newFile = $file;
        $imageUuid = $file->uuid();
      }
      $imageChanged = $imageUuid !== (string) ($previous['image_uuid'] ?? '');
      $version = (int) ($previous['criterion_version'] ?? 1);
      $previousTriggers = $previous['triggers'] ?? [];
      $comparison = $triggers;
      sort($previousTriggers);
      sort($comparison);
      if ($previous !== NULL && $previousTriggers !== $comparison) {
        $version++;
      }
      $this->configs->getEditable('aculta_user.achievement_definitions')->set('definitions.' . $id, [
        'label' => trim((string) $definition['label']),
        'description' => trim((string) ($definition['description'] ?? '')),
        'enabled' => !empty($definition['enabled']),
        'triggers' => $triggers,
        'criterion_version' => $version,
        'image' => !empty($definition['remove_image']) ? '' : (in_array($definition['image'] ?? $previous['image'] ?? '', ['fundadore', 'associado', 'apoiador'], TRUE) ? ($definition['image'] ?? $previous['image']) : ''),
        'image_uuid' => $imageUuid,
      ])->save();
      if ($imageChanged && $oldFile) {
        $this->fileUsage->delete($oldFile, 'aculta_user', 'achievement', $id);
        if (!empty($definition['remove_image']) && $this->fileUsage->listUsage($oldFile) === []) {
          $oldFile->delete();
        }
      }
      if ($imageChanged && $newFile) {
        $this->fileUsage->add($newFile, 'aculta_user', 'achievement', $id);
      }
    }
    finally {
      $this->lock->release($name);
    }
  }

  /** Detach the image and delete its managed file when it has no other usage. */
  public function removeImage(string $id): void {
    $definition = $this->all()[$id] ?? NULL;
    if ($definition === NULL) {
      throw new \InvalidArgumentException('Conquista não encontrada.');
    }
    $this->save($id, $definition + ['remove_image' => TRUE, 'image_fid' => NULL]);
  }

}
