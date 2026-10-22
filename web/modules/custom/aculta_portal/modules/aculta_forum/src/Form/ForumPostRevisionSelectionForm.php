<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\aculta_forum\Entity\ForumPost;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Selects two visible revisions of a Forum post for comparison.
 */
final class ForumPostRevisionSelectionForm extends FormBase {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_post_revision_selection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $revisionOptions = []): array {
    if (count($revisionOptions) < 2) {
      return $form;
    }
    $form_state->set('revision_options', $revisionOptions);
    $revisionIds = array_map('intval', array_keys($revisionOptions));
    sort($revisionIds, SORT_NUMERIC);
    $form['older_revision'] = [
      '#type' => 'select',
      '#title' => $this->t('Revisão mais antiga'),
      '#options' => $revisionOptions,
      '#default_value' => (string) $revisionIds[0],
      '#required' => TRUE,
    ];
    $form['newer_revision'] = [
      '#type' => 'select',
      '#title' => $this->t('Revisão mais recente'),
      '#options' => $revisionOptions,
      '#default_value' => (string) end($revisionIds),
      '#required' => TRUE,
    ];
    $form['actions']['compare'] = [
      '#type' => 'submit',
      '#value' => $this->t('Comparar revisões selecionadas'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $available = $form_state->get('revision_options') ?? [];
    $older = (string) $form_state->getValue('older_revision');
    $newer = (string) $form_state->getValue('newer_revision');
    if (!isset($available[$older], $available[$newer]) || $older === $newer) {
      $form_state->setErrorByName('newer_revision', $this->t('Selecione duas revisões diferentes desta publicação.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $post = $this->routeMatch->getParameter('aculta_forum_post');
    if (!$post instanceof ForumPost) {
      $form_state->setErrorByName('newer_revision', $this->t('Não foi possível identificar a publicação.'));
      return;
    }
    $revisionIds = [
      (int) $form_state->getValue('older_revision'),
      (int) $form_state->getValue('newer_revision'),
    ];
    sort($revisionIds, SORT_NUMERIC);
    $form_state->setRedirectUrl(Url::fromRoute('aculta_forum.post_revision_compare', [
      'aculta_forum_post' => $post->id(),
      'left_revision' => $revisionIds[0],
      'right_revision' => $revisionIds[1],
    ]));
  }

}
