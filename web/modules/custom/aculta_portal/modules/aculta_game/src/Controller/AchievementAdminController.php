<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Controller;

use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Catálogo administrativo; permissões e Domain são aplicados nas rotas. */
final class AchievementAdminController extends ControllerBase {

  public function __construct(private readonly AchievementDefinitions $definitions) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.achievement_definitions'));
  }

  public function overview(): array {
    $rows = [];
    foreach ($this->definitions->all() as $id => $definition) {
      $triggers = array_map(fn (string $trigger) => $this->t(AchievementDefinitions::TRIGGERS[$trigger]), $definition['triggers']);
      $rows[] = [
        $definition['label'],
        implode(', ', $triggers),
        $definition['enabled'] ? $this->t('Ativa') : $this->t('Pausada'),
        $definition['criterion_version'],
        ['data' => ['#type' => 'link', '#title' => $this->t('Editar'), '#url' => Url::fromRoute('aculta_game.achievement_edit', ['conquista' => $id])]],
      ];
    }
    return [
      'create' => ['#type' => 'link', '#title' => $this->t('Criar conquista'), '#url' => Url::fromRoute('aculta_game.achievement_add')],
      'grant' => ['#type' => 'link', '#title' => $this->t('Conceder ou revogar conquista'), '#url' => Url::fromRoute('aculta_game.achievement_grant')],
      'table' => ['#type' => 'table', '#header' => [$this->t('Conquista'), $this->t('Formas de concessão'), $this->t('Disponibilidade'), $this->t('Critério'), $this->t('Operações')], '#rows' => $rows],
      '#cache' => ['contexts' => ['user.permissions', 'languages:language_interface'], 'tags' => ['config:aculta_user.achievement_definitions']],
    ];
  }

}
