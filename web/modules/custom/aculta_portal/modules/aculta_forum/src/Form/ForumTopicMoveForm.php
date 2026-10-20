<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\ForumTopicMover;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Explicitly confirms moving a public topic to another category.
 */
final class ForumTopicMoveForm extends FormBase {

  public function __construct(
    private readonly ForumTopicMover $mover,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.topic_mover'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_forum_topic_move';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || !$this->mover->access($node, $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('topic_id', (int) $node->id());
    $current_term_id = (int) ($node->get('field_aculta_categories')->getValue()[0]['target_id'] ?? 0);
    $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('aculta_forum_categories', 0, NULL, TRUE);
    $options = [];
    foreach ($tree as $term) {
      if ($term->id() !== $current_term_id && $term->access('view', $this->account)) {
        $options[$term->id()] = str_repeat('— ', (int) $term->depth) . $term->label();
      }
    }
    if ($options === []) {
      throw new AccessDeniedHttpException('No public destination category is available.');
    }

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Tópico'),
      '#markup' => $this->t('O tópico “@title” continuará sendo o mesmo conteúdo; somente sua categoria será alterada.', ['@title' => $node->label()]),
    ];
    $form['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Categoria de destino'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['confirm_move'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo mover este tópico para a categoria selecionada.'),
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Mover tópico'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('aculta_forum.admin'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $destination = filter_var($form_state->getValue('destination'), FILTER_VALIDATE_INT);
    if ($destination === FALSE || $destination <= 0 || !$form_state->getValue('confirm_move')) {
      $form_state->setErrorByName('destination', $this->t('Selecione uma categoria e confirme a movimentação.'));
    }
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $node = $this->mover->move((int) $form_state->get('topic_id'), (int) $form_state->getValue('destination'), $this->account);
      $category = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $node->get('field_aculta_categories')->getValue()[0]['target_id']);
      $this->messenger()->addStatus($this->t('O tópico foi movido para “@category”.', ['@category' => $category?->label() ?? '']));
      $form_state->setRedirect('aculta_forum.admin');
    }
    catch (\InvalidArgumentException) {
      $this->messenger()->addError($this->t('Não foi possível mover este tópico. A categoria, o acesso ou o contexto mudaram; nenhuma alteração foi aplicada.'));
      $form_state->setRedirect('aculta_forum.admin');
    }
  }

}
