<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Service\TopicDeletionModerationManager;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for the topic deletion moderation queue.
 */
class TopicDeletionQueueController extends ControllerBase {

  public function __construct(
    protected readonly TopicDeletionModerationManager $moderationManager,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly CsrfTokenGenerator $csrfTokenGenerator,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('aculta_forum.topic_deletion_moderation_manager'),
      $container->get('date.formatter'),
      $container->get('csrf_token')
    );
  }

  /**
   * Displays topics awaiting deletion moderation.
   */
  public function queue(): array {
    $requests = $this->moderationManager->getPendingRequests();
    $rows = [];

    foreach ($requests as $item) {
      $topic = $item['topic'];
      $requester = $item['requester'];
      $id = $item['id'];

      $topicTitle = $topic instanceof NodeInterface ? $topic->label() : $this->t('Tópico ID @nid (inexistente)', ['@nid' => $item['nid']]);
      $topicLink = $topic instanceof NodeInterface ? Link::fromTextAndUrl($topicTitle, $topic->toUrl())->toString() : $topicTitle;

      $requesterName = $requester ? $requester->getDisplayName() : $this->t('Usuário ID @uid', ['@uid' => $item['uid']]);
      $date = $this->dateFormatter->format($item['created'], 'short');

      $approveToken = $this->csrfTokenGenerator->get("topic_deletion_approve:{$id}");
      $rejectToken = $this->csrfTokenGenerator->get("topic_deletion_reject:{$id}");

      $approveUrl = Url::fromRoute('aculta_forum.topic_deletion_approve', ['id' => $id], ['query' => ['token' => $approveToken]]);
      $rejectUrl = Url::fromRoute('aculta_forum.topic_deletion_reject', ['id' => $id], ['query' => ['token' => $rejectToken]]);

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

  /**
   * Approves deletion of a topic.
   */
  public function approve(int $id, Request $request): RedirectResponse {
    $token = $request->query->get('token');
    if (!$token || !$this->csrfTokenGenerator->validate((string) $token, "topic_deletion_approve:{$id}")) {
      throw new AccessDeniedHttpException('Token CSRF inválido.');
    }

    $success = $this->moderationManager->approveDeletion($id, $this->currentUser());
    if ($success) {
      $this->messenger()->addStatus($this->t('Solicitação de exclusão aprovada e tópico excluído definitivamente.'));
    }
    else {
      $this->messenger()->addError($this->t('Não foi possível processar a solicitação de exclusão.'));
    }

    return $this->redirect('aculta_forum.topic_deletion_queue');
  }

  /**
   * Rejects deletion of a topic, restoring it.
   */
  public function reject(int $id, Request $request): RedirectResponse {
    $token = $request->query->get('token');
    if (!$token || !$this->csrfTokenGenerator->validate((string) $token, "topic_deletion_reject:{$id}")) {
      throw new AccessDeniedHttpException('Token CSRF inválido.');
    }

    $success = $this->moderationManager->rejectDeletion($id, $this->currentUser());
    if ($success) {
      $this->messenger()->addStatus($this->t('Solicitação de exclusão rejeitada e tópico restaurado com sucesso.'));
    }
    else {
      $this->messenger()->addError($this->t('Não foi possível processar a rejeição da solicitação.'));
    }

    return $this->redirect('aculta_forum.topic_deletion_queue');
  }

}
