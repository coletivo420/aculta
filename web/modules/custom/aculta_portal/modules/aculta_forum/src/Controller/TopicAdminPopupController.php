<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_forum\Service\TopicModerationAudit;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fragment for the topic moderation popup (comment-admin-trigger kind=topico).
 *
 * Renders topic-admin-controls: read-only facts, allowed actions and the CGU decision log for the topic.
 * Route access is checked by permission; the fragment only links to protected routes.
 */
final class TopicAdminPopupController extends ControllerBase {

  /**
   * Creates the controller with the database and date formatter.
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    $instance->audit = $container->get('aculta_forum.topic_moderation_audit');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * The database connection used for reaction counts.
   */
  private Connection $database;

  /**
   * Administrative audit of split and merge actions.
   */
  private TopicModerationAudit $audit;

  /**
   * The date formatter used for the publication date.
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * Returns the topic moderation popup content.
   *
   * @return array<string, mixed>
   *   Render array for topic-admin-controls.
   */
  public function view(NodeInterface $node): array {
    $nid = (int) $node->id();
    $owner = $node->getOwner();
    $authorName = (string) $owner->getDisplayName();
    $categoryLabel = '—';
    if ($node->hasField('field_aculta_categories') && !$node->get('field_aculta_categories')->isEmpty()) {
      $category = $node->get('field_aculta_categories')->entity;
      if ($category !== NULL) {
        $categoryLabel = (string) $category->label();
      }
    }

    $replyValue = $this->entityTypeManager()->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $nid)
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('status', 1)
      ->count()
      ->execute();
    $replyCount = (int) $replyValue;

    $reactionValue = $this->database->select('aculta_discussion_reaction', 'r')
      ->condition('r.entity_type', 'node')
      ->condition('r.entity_id', $nid)
      ->countQuery()
      ->execute()
      ?->fetchField();
    $reactionTotal = is_scalar($reactionValue) ? (int) $reactionValue : 0;

    $items = [];
    $reports = $this->entityTypeManager()->getStorage('aculta_cgu_report')->loadByProperties([
      'target_type' => 'node',
      'target_id' => $nid,
    ]);
    foreach ($reports as $report) {
      if (!$report instanceof CguReport) {
        continue;
      }
      $statusField = $report->get('status');
      $status = is_string($statusField->value ?? NULL) ? $statusField->value : '';
      if ($status === 'pendente') {
        continue;
      }
      $notesField = $report->get('decision_notes');
      $notes = is_string($notesField->value ?? NULL) ? $notesField->value : '';
      $items[] = $status . ($notes !== '' ? ': ' . $notes : '');
    }
    // Auditoria administrativa de dividir e juntar (sem exibição pública).
    $labels = ['split' => (string) $this->t('Dividiu'), 'merge' => (string) $this->t('Juntou')];
    foreach ($this->audit->entries((int) $node->id()) as $entry) {
      $user = $this->entityTypeManager()->getStorage('user')->load($entry['uid']);
      $name = $user !== NULL ? (string) $user->getDisplayName() : (string) $this->t('Usuário removido');
      $items[] = (string) $this->t('@action @count comentário(s) para o tópico @dest por @name em @date', [
        '@action' => $labels[$entry['action']] ?? $entry['action'],
        '@count' => (string) $entry['count'],
        '@dest' => (string) $entry['destination'],
        '@name' => $name,
        '@date' => $this->dateFormatter->format($entry['time'], 'short'),
      ]);
    }
    $log = $items === [] ? NULL : [
      '#theme' => 'item_list',
      '#items' => $items,
    ];

    // Controles do tópico em paridade com o Discourse, somente os que têm rota e acesso no código.
    $actions = [];
    if ($node->access('update')) {
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Editar tópico'),
        '#url' => Url::fromRoute('aculta_forum.discussion_composer', ['entity_type' => 'node', 'entity_id' => $nid], ['query' => ['editar_topico' => 1]]),
      ];
    }
    if ($this->currentUser()->hasPermission('move aculta forum topics')) {
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Mover tópico de categoria'),
        '#url' => Url::fromRoute('aculta_forum.topic_move', ['node' => $nid]),
      ];
    }
    if ($node->access('delete')) {
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Excluir tópico'),
        '#url' => Url::fromRoute('aculta_forum.discussion_composer', ['entity_type' => 'node', 'entity_id' => $nid], ['query' => ['excluir_topico' => 1]]),
      ];
    }

    if ($this->currentUser()->hasPermission('administer comments')) {
      $actions[] = [
        '#type' => 'link',
        '#title' => $node->isPublished() ? $this->t('Despublicar tópico') : $this->t('Republicar tópico'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_publish', ['node' => $nid]),
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Arquivar ou desarquivar tópico'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_archive', ['node' => $nid]),
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Dividir tópico'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_split', ['node' => $nid]),
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Juntar tópico'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_merge', ['node' => $nid]),
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => $node->isSticky() ? $this->t('Desafixar tópico') : $this->t('Fixar tópico'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_pin', ['node' => $nid]),
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Fechar ou reabrir tópico'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_close', ['node' => $nid]),
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Remover pela moderação'),
        '#url' => Url::fromRoute('aculta_forum.topic_moderation_remove', ['node' => $nid]),
      ];
    }

    $published = $node->isPublished();
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:topic-admin-controls',
      '#props' => [
        'author_name' => $authorName,
        'state_label' => (string) ($published ? $this->t('Publicado') : $this->t('Despublicado')),
        'category_label' => $categoryLabel,
        'created_label' => $this->dateFormatter->format($node->getCreatedTime(), 'custom', 'd/m/Y H:i'),
        'reactions_total' => $reactionTotal,
        'reply_count' => $replyCount,
      ],
      '#slots' => [
        'actions' => array_map(fn(array $action): array => $this->actionComponent($action, $node), $actions),
        'log' => $log ?? [],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), ['aculta_cgu_report_list']),
      ],
    ];
  }

  /**
   * Renders one moderation action as the moderation-action component (Phosphor icon).
   *
   * @param array<string, mixed> $action
   *   Link render array built above (#title, #url).
   * @param \Drupal\node\NodeInterface $node
   *   The topic.
   *
   * @return array<string, mixed>
   *   Component render array.
   */
  private function actionComponent(array $action, NodeInterface $node): array {
    $url = $action['#url'] ?? NULL;
    if (!$url instanceof Url) {
      throw new \LogicException('Moderation action without a Url.');
    }
    $route = $url->getRouteName();
    $rawQuery = $url->getOption('query');
    $query = is_array($rawQuery) ? $rawQuery : [];
    // Cor por tipo de ação; ícone duotone para ações de alto impacto, regular para as demais.
    [$icon, $tone] = match (TRUE) {
      $route === 'aculta_forum.discussion_composer' && isset($query['excluir_topico']) => ['trash', 'danger'],
      $route === 'aculta_forum.discussion_composer' => ['pencil-simple', 'primary'],
      $route === 'aculta_forum.topic_move' => ['arrows-left-right', 'primary'],
      $route === 'aculta_forum.topic_moderation_pin' => [
        $node->isSticky() ? 'push-pin-simple-slash' : 'push-pin-simple',
        'primary',
      ],
      $route === 'aculta_forum.topic_moderation_close' => ['lock-simple', 'warning'],
      $route === 'aculta_forum.topic_moderation_remove' => ['prohibit', 'danger'],
      $route === 'aculta_forum.topic_moderation_publish' => $node->isPublished()
        ? ['eye-slash', 'warning']
        : ['eye', 'success'],
      $route === 'aculta_forum.topic_moderation_archive' => ['archive', 'neutral'],
      $route === 'aculta_forum.topic_moderation_split' => ['arrows-split', 'primary'],
      $route === 'aculta_forum.topic_moderation_merge' => ['arrows-merge', 'primary'],
      default => ['dots-three', 'neutral'],
    };
    $variant = in_array($tone, ['danger', 'warning'], TRUE) ? 'duotone' : 'regular';
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:moderation-action',
      '#props' => [
        'label' => $this->labelOf($action),
        'url' => $url->toString(),
        'icon' => $icon,
        'variant' => $variant,
        'tone' => $tone,
      ],
    ];
  }

  /**
   * Plain-text label of a link render array.
   *
   * @param array<string, mixed> $action
   *   Link render array.
   */
  private function labelOf(array $action): string {
    $title = $action['#title'] ?? '';
    return $title instanceof \Stringable || is_string($title) ? (string) $title : '';
  }

}
