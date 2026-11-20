<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\ForumCommentMover;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation split (Discourse parity: dividir tópicos).
 *
 * Moves the selected top-level comments, with their replies, to another topic.
 */
final class TopicSplitForm extends FormBase {

  /**
   * Creates the form.
   */
  public function __construct(
    protected readonly ForumCommentMover $mover,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_topic_split_form';
  }

  /**
   * The source topic from the route.
   */
  private function topic(): ?NodeInterface {
    $node = $this->topicRoute->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
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
    $storage = $this->entityTypeManager->getStorage('comment');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('pid', NULL, 'IS NULL')
      ->execute();
    $comments = $storage->loadMultiple($ids);
    $choices = [];
    foreach ($comments as $comment) {
      $choices[(int) $comment->id()] = (string) $comment->getSubject();
    }
    $destinations = [];
    if ($comments !== []) {
      $first = reset($comments);
      $destinations = $this->mover->destinations($first, $this->account);
    }
    unset($destinations[$topic->id()]);
    if ($choices === [] || $destinations === []) {
      $form['empty'] = ['#markup' => $this->t('Não há comentários de nível principal ou tópico de destino acessível para dividir.')];
      return $form;
    }
    $form['comments'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Comentários a mover (com suas respostas)'),
      '#options' => $choices,
      '#required' => TRUE,
    ];
    $form['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Tópico de destino'),
      '#options' => $destinations,
      '#required' => TRUE,
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo mover os comentários selecionados.'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => ['#type' => 'submit', '#value' => $this->t('Dividir tópico'), '#button_type' => 'primary'],
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
    $raw = $form_state->getValue('destination');
    $destination = is_numeric($raw) ? (int) $raw : 0;
    $selected = array_filter((array) $form_state->getValue('comments'));
    foreach (array_keys($selected) as $commentId) {
      $this->mover->move((int) $commentId, $destination, $this->account);
    }
    $this->messenger()->addStatus($this->t('Comentários movidos. Tópico dividido.'));
    $form_state->setRedirect('entity.node.canonical', ['node' => $destination]);
  }

}
