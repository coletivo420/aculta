<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\ForumCommentMover;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;
use Drupal\aculta_forum\Service\TopicModerationAudit;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation merge (Discourse parity: juntar tópicos).
 *
 * Moves every top-level comment of this topic, with its replies, into the
 * chosen destination topic, then closes this topic with a notice.
 */
final class TopicMergeForm extends FormBase {

  /**
   * Creates the form.
   */
  public function __construct(
    protected readonly ForumCommentMover $mover,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TopicAuthorDeletion $state,
    protected readonly TopicModerationAudit $audit,
    protected readonly RouteMatchInterface $topicRoute,
    protected readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.comment_mover'),
      $container->get('entity_type.manager'),
      $container->get('aculta_forum.topic_author_deletion'),
      $container->get('aculta_forum.topic_moderation_audit'),
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_topic_merge_form';
  }

  /**
   * The source topic from the route.
   */
  private function topic(): ?NodeInterface {
    $node = $this->topicRoute->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Top-level comment ids of the source topic.
   *
   * @return int[]
   *   Comment ids without a parent.
   */
  private function rootCommentIds(NodeInterface $topic): array {
    $ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('pid', NULL, 'IS NULL')
      ->execute();
    return array_map('intval', array_values($ids));
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array<string, mixed>
   *   Form render array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $topic = $this->topic();
    if (!$topic instanceof NodeInterface || !$this->account->hasPermission('move aculta forum topics')) {
      return ['#markup' => $this->t('Tópico indisponível.')];
    }
    $roots = $this->rootCommentIds($topic);
    $options = [];
    if ($roots !== []) {
      $comment = $this->entityTypeManager->getStorage('comment')->load($roots[0]);
      if ($comment !== NULL) {
        $options = $this->mover->destinations($comment, $this->account);
      }
    }
    unset($options[$topic->id()]);
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Tópico de origem'),
      '#plain_text' => $this->t('“@title” terá @count comentário(s) de nível principal movido(s), com suas respostas. O tópico será fechado e ficará com aviso de junção.', [
        '@title' => (string) $topic->label(),
        '@count' => (string) count($roots),
      ]),
    ];
    if ($options === [] || $roots === []) {
      $form['empty'] = ['#markup' => $this->t('Não há tópico de destino acessível ou comentários para juntar.')];
      return $form;
    }
    $form['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Tópico de destino'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo juntar este tópico ao destino.'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => ['#type' => 'submit', '#value' => $this->t('Juntar tópicos'), '#button_type' => 'primary'],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancelar'),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $topic->id()]),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $topic = $this->topic();
    if (!$topic instanceof NodeInterface) {
      return;
    }
    $raw = $form_state->getValue('destination');
    $destination = is_numeric($raw) ? (int) $raw : 0;
    $roots = $this->rootCommentIds($topic);
    foreach ($roots as $commentId) {
      $this->mover->move($commentId, $destination, $this->account);
    }
    $this->audit->record((int) $topic->id(), 'merge', $destination, (int) $this->account->id(), time(), count($roots));
    $this->state->setClosed($topic, TRUE);
    $this->messenger()->addStatus($this->t('Tópicos juntados. O tópico de origem foi fechado.'));
    $form_state->setRedirect('entity.node.canonical', ['node' => $destination]);
  }

}
