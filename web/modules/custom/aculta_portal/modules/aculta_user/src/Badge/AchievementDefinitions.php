<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Badge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;

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
    foreach (BadgeCatalog::all() as $id => $definition) {
      $defaults[$id] = $definition + ['description' => '', 'enabled' => TRUE, 'triggers' => $triggers[$id], 'criterion_version' => 1, 'image' => in_array($id, ['fundadore', 'associado', 'apoiador'], TRUE) ? $id : ''];
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
      $version = (int) ($previous['criterion_version'] ?? 1);
      if ($previous !== NULL && $previous['triggers'] !== $triggers) {
        $version++;
      }
      $this->configs->getEditable('aculta_user.achievement_definitions')->set('definitions.' . $id, [
        'label' => trim((string) $definition['label']),
        'description' => trim((string) ($definition['description'] ?? '')),
        'enabled' => !empty($definition['enabled']),
        'triggers' => $triggers,
        'criterion_version' => $version,
        'image' => in_array($definition['image'] ?? '', ['fundadore', 'associado', 'apoiador'], TRUE) ? $definition['image'] : '',
      ])->save();
    }
    finally {
      $this->lock->release($name);
    }
  }

}
