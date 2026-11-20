<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_cgu\Entity\CguReport;
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
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * The database connection used for reaction counts.
   */
  private Connection $database;

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
    $log = $items === [] ? NULL : [
      '#theme' => 'item_list',
      '#items' => $items,
    ];

    $actions = [];
    if ($this->currentUser()->hasPermission('move aculta forum topics')) {
      $actions[] = [
        '#type' => 'link',
        '#title' => $this->t('Mover tópico de categoria'),
        '#url' => Url::fromRoute('aculta_forum.topic_move', ['node' => $nid]),
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
        'actions' => $actions === [] ? [] : $actions,
        'log' => $log ?? [],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), ['aculta_cgu_report_list']),
      ],
    ];
  }

}
