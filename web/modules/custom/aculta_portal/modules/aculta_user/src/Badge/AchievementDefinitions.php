<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Badge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Catálogo configurável de critérios fechados; User preserva definições e fatos.
 */
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

  /**
   * Retorna todas as conquistas, combinando padrões e configuração.
   *
   * @return array<string, array<array-key, mixed>>
   *   Definições de conquistas indexadas pelo identificador.
   */
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
      // Sem gatilho: concedidos só pelo ForumItemMilestoneAwarder, com limiar por item.
      BadgeCatalog::TOPICO_LEGAL_FORUM => [],
      BadgeCatalog::TOPICO_BOM_FORUM => [],
      BadgeCatalog::TOPICO_EXCELENTE_FORUM => [],
      BadgeCatalog::RESPOSTA_LEGAL_FORUM => [],
      BadgeCatalog::RESPOSTA_BOA_FORUM => [],
      BadgeCatalog::RESPOSTA_EXCELENTE_FORUM => [],
    ];
    $legacy = [
      BadgeCatalog::FUNDADORE => [
        'Reconhece quem participou da criação da Associação Cultural Antiproibicionista e da construção inicial do Coletivo 420.',
        'fundadore',
      ],
      BadgeCatalog::ASSOCIADO => [
        'Reconhecimento da participação na categoria Membro Antiproibicionista do Coletivo 420.',
        'associado',
      ],
      BadgeCatalog::APOIADOR => [
        'Reconhece o apoio e a participação nas atividades e nos ideais do Coletivo 420.',
        'apoiador',
      ],
    ];
    foreach (BadgeCatalog::all() as $id => $definition) {
      $defaults[$id] = $definition + [
        'description' => $legacy[$id][0] ?? '',
        'enabled' => TRUE,
        'triggers' => $triggers[$id],
        'criterion_version' => 1,
        'image' => $legacy[$id][1] ?? '',
        'image_uuid' => '',
      ];
    }
    $configured = $this->configs->get('aculta_user.achievement_definitions')->get('definitions');
    foreach ($this->arrayValue($configured) as $id => $definition) {
      if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', (string) $id) || !is_array($definition) || empty($definition['label'])) {
        continue;
      }
      $definition['triggers'] = $this->triggersFrom($definition['triggers'] ?? NULL);
      $definition['origem'] = $definition['triggers'] === ['manual'] ? BadgeCatalog::MANUAL : BadgeCatalog::AUTOMATICA;
      $defaults[(string) $id] = $definition;
    }
    return $defaults;
  }

  /**
   * Salva um item sem sobrescrever edições concorrentes dos demais.
   *
   * @param array<array-key, mixed> $definition
   *   Campos da conquista a gravar.
   */
  public function save(string $id, array $definition): void {
    if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $id) || trim($this->textValue($definition['label'] ?? '')) === '') {
      throw new \InvalidArgumentException('Identificador ou nome de conquista inválido.');
    }
    $triggers = $this->triggersFrom($definition['triggers'] ?? NULL);
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
      $imageUuid = $this->textValue($previous['image_uuid'] ?? '');
      $oldFiles = !empty($previous['image_uuid']) ? $this->entities->getStorage('file')->loadByProperties(['uuid' => $previous['image_uuid']]) : [];
      $oldFile = $oldFiles ? reset($oldFiles) : NULL;
      $newFile = NULL;
      if (!empty($definition['remove_image'])) {
        $imageUuid = '';
      }
      if (!empty($definition['image_fid'])) {
        $file = $this->entities->getStorage('file')->load($this->intValue($definition['image_fid']));
        $uri = $file?->getFileUri();
        if (!$file || !is_string($uri) || !str_starts_with($uri, 'public://') || !str_starts_with($this->textValue($file->getMimeType()), 'image/')) {
          throw new \InvalidArgumentException('Envie uma imagem pública válida.');
        }
        $file->setPermanent();
        $file->save();
        $newFile = $file;
        $imageUuid = $file->uuid();
      }
      $imageChanged = $imageUuid !== $this->textValue($previous['image_uuid'] ?? '');
      $version = $this->intValue($previous['criterion_version'] ?? 1);
      $previousTriggers = $this->arrayValue($previous['triggers'] ?? NULL);
      $comparison = $triggers;
      sort($previousTriggers);
      sort($comparison);
      if ($previous !== NULL && $previousTriggers !== $comparison) {
        $version++;
      }
      $imageKey = $definition['image'] ?? ($previous['image'] ?? '');
      $this->configs->getEditable('aculta_user.achievement_definitions')->set('definitions.' . $id, [
        'label' => trim($this->textValue($definition['label'])),
        'description' => trim($this->textValue($definition['description'] ?? '')),
        'enabled' => !empty($definition['enabled']),
        'triggers' => $triggers,
        'criterion_version' => $version,
        'image' => !empty($definition['remove_image']) ? '' : (in_array($imageKey, [
          'fundadore',
          'associado',
          'apoiador',
        ], TRUE) ? $imageKey : ''),
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

  /**
   * Detach the image and delete its managed file when it has no other usage.
   */
  public function removeImage(string $id): void {
    $definition = $this->all()[$id] ?? NULL;
    if ($definition === NULL) {
      throw new \InvalidArgumentException('Conquista não encontrada.');
    }
    $this->save($id, $definition + ['remove_image' => TRUE, 'image_fid' => NULL]);
  }

  /**
   * Gatilhos pedidos que existem no catálogo, na ordem do catálogo.
   *
   * @return list<string>
   *   Chaves de TRIGGERS presentes no valor pedido.
   */
  private function triggersFrom(mixed $value): array {
    // Só texto pode ser gatilho: as chaves de TRIGGERS são strings não numéricas.
    $requested = [];
    foreach ($this->arrayValue($value) as $item) {
      if (is_string($item)) {
        $requested[] = $item;
      }
    }
    return array_values(array_intersect(array_keys(self::TRIGGERS), $requested));
  }

  /**
   * O valor como array, ou vazio quando não é array.
   *
   * @return array<array-key, mixed>
   *   Valor recebido, ou lista vazia.
   */
  private function arrayValue(mixed $value): array {
    return is_array($value) ? $value : [];
  }

  /**
   * Texto de um valor de configuração; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
  }

  /**
   * Inteiro de um valor de configuração; zero quando o valor não é numérico.
   */
  private function intValue(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

}
