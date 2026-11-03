<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Controller;

use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Catálogo administrativo; permissões e Domain são aplicados nas rotas.
 */
final class AchievementAdminController extends ControllerBase {

  public function __construct(
    private readonly AchievementDefinitions $definitions,
    private readonly EntityTypeManagerInterface $entities,
    private readonly DateFormatterInterface $dates,
  ) {}

  /**
   * Create the administrative controller from Drupal services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.achievement_definitions'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Build the administrative achievement catalog.
   */
  public function overview(): array {
    $rows = [];
    foreach ($this->definitions->all() as $id => $definition) {
      $triggers = array_map(
        fn (string $trigger): TranslatableMarkup => $this->triggerLabel($trigger),
        $definition['triggers'],
      );
      $rows[] = [
        $definition['label'],
        implode(', ', $triggers),
        $definition['enabled'] ? $this->t('Ativa') : $this->t('Pausada'),
        $definition['criterion_version'],
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Editar'),
            '#url' => Url::fromRoute('aculta_game.achievement_edit', ['conquista' => $id]),
          ],
        ],
      ];
    }
    return [
      'create' => [
        '#type' => 'link',
        '#title' => $this->t('Criar conquista'),
        '#url' => Url::fromRoute('aculta_game.achievement_add'),
      ],
      'grant' => [
        '#type' => 'link',
        '#title' => $this->t('Conceder ou revogar conquista'),
        '#url' => Url::fromRoute('aculta_game.achievement_grant'),
      ],
      'history' => [
        '#type' => 'link',
        '#title' => $this->t('Histórico de concessões'),
        '#url' => Url::fromRoute('aculta_game.achievement_history'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Conquista'),
          $this->t('Formas de concessão'),
          $this->t('Disponibilidade'),
          $this->t('Critério'),
          $this->t('Operações'),
        ],
        '#rows' => $rows,
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'languages:language_interface'],
        'tags' => ['config:aculta_user.achievement_definitions'],
      ],
    ];
  }

  /**
   * Read persisted awards without creating a second history store.
   */
  public function history(): array {
    $storage = $this->entities->getStorage('aculta_badge_award');
    // A rota exige permissão administrativa; a entidade
    // não tem coleção pública.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('concedido_em', 'DESC')
      ->sort('id', 'DESC')
      ->pager(50);
    $definitions = $this->definitions->all();
    $rows = [];
    foreach ($storage->loadMultiple($query->execute()) as $award) {
      $id = (string) $award->get('badge')->value;
      $rows[] = [
        $award->get('uid')->entity?->getAccountName() ?? $this->t('Conta indisponível'),
        $definitions[$id]['label'] ?? $id,
        $award->get('origem')->value === 'manual' ? $this->t('Administrativa') : $this->t('Automática'),
        $this->dates->format((int) $award->get('concedido_em')->value, 'short'),
        $award->get('concedido_por')->entity?->getAccountName() ?? '—',
        (string) $award->get('motivo_concessao')->value,
        $award->get('revogado_em')->isEmpty() ? $this->t('Ativa') : $this->t('Revogada'),
        $award->get('revogado_em')->isEmpty()
          ? '—'
          : $this->dates->format((int) $award->get('revogado_em')->value, 'short'),
        $award->get('revogado_por')->entity?->getAccountName() ?? '—',
        (string) $award->get('motivo_revogacao')->value,
      ];
    }
    return [
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Pessoa'),
          $this->t('Conquista'),
          $this->t('Origem'),
          $this->t('Obtida em'),
          $this->t('Concedida por'),
          $this->t('Motivo da concessão'),
          $this->t('Estado'),
          $this->t('Revogada em'),
          $this->t('Revogada por'),
          $this->t('Motivo da revogação'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma concessão registrada.'),
      ],
      'pager' => ['#type' => 'pager'],
      '#cache' => ['contexts' => ['user.permissions'], 'max-age' => 0],
    ];
  }

  /**
   * Return the translated label for a fixed achievement criterion.
   */
  private function triggerLabel(string $trigger): TranslatableMarkup {
    return match ($trigger) {
      'manual' => $this->t('Concessão administrativa'),
      'order_completed' => $this->t('Pedido concluído'),
      'donation_completed' => $this->t('Apoio concluído no sistema de doações'),
      'forum_comment' => $this->t('Resposta publicada no Growroom420'),
      'wiki_revision' => $this->t('Revisão publicada na Wiki420'),
      'course_passed' => $this->t('Curso concluído com aprovação'),
      'profile_basic' => $this->t('Perfil básico completo'),
      default => throw new \InvalidArgumentException('Critério de conquista desconhecido.'),
    };
  }

}
