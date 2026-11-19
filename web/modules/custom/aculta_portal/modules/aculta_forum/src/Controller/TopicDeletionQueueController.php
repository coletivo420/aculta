<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Service\TopicDeletionModerationManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the topic deletion moderation queue.
 */
final class TopicDeletionQueueController extends ControllerBase {

  /**
   * Creates the queue controller.
   *
   * @param \Drupal\aculta_forum\Service\TopicDeletionModerationManager $moderationManager
   *   The topic deletion moderation manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    protected readonly TopicDeletionModerationManager $moderationManager,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the controller from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A controller instance.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.topic_deletion_moderation_manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * Displays topics awaiting deletion moderation.
   *
   * @return array<string, mixed>
   *   The moderation queue render array.
   */
  public function queue(): array {
    $requests = $this->moderationManager->getPendingRequests();
    $rows = [];

    foreach ($requests as $item) {
      $topic = $item['topic'];
      $requester = $item['requester'];
      $id = $item['id'];

      $topicTitle = $topic instanceof NodeInterface ? $topic->label() : (string) $this->t('Tópico ID @nid (inexistente)', ['@nid' => $item['nid']]);
      $topicLink = $topic instanceof NodeInterface ? Link::fromTextAndUrl($topicTitle ?? '', $topic->toUrl())->toString() : $topicTitle;

      $requesterName = $requester ? $requester->getDisplayName() : $this->t('Usuário ID @uid', ['@uid' => $item['uid']]);
      $date = $this->dateFormatter->format($item['created'], 'short');

      // Links open Drupal confirmation forms. GET/HEAD never execute a change.
      $approveUrl = Url::fromRoute('aculta_forum.topic_deletion_approve', ['id' => $id]);
      $rejectUrl = Url::fromRoute('aculta_forum.topic_deletion_reject', ['id' => $id]);

      $actions = [
        'data' => [
          '#type' => 'operations',
          '#links' => [
            'approve' => [
              'title' => $this->t('Aprovar e excluir'),
              'url' => $approveUrl,
            ],
            'reject' => [
              'title' => $this->t('Rejeitar e restaurar'),
              'url' => $rejectUrl,
            ],
          ],
        ],
      ];

      $rows[] = [
        $id,
        ['data' => ['#markup' => $topicLink]],
        $requesterName,
        $date,
        $actions,
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Tópico'),
        $this->t('Solicitante'),
        $this->t('Data da solicitação'),
        $this->t('Operações'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Não há solicitações de exclusão de tópicos pendentes.'),
    ];

    return $build;
  }

}
