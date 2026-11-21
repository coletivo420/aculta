<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
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
    protected readonly RouteMatchInterface $forumRouteMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_post_revision_selection';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   * @param array<int|string, mixed> $revisionOptions
   *   Value used for $revisionOptions.
   * @param ?int $olderRevisionId
   *   Value used for $olderRevisionId.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $revisionOptions = [], ?int $olderRevisionId = NULL): array {
    if ($revisionOptions === []) {
      return $form;
    }
    $revisionIds = [];
    foreach (array_keys($revisionOptions) as $key) {
      $id = filter_var($key, FILTER_VALIDATE_INT);
      if ($id !== FALSE && $id > 0) {
        $revisionIds[] = (int) $id;
      }
    }
    if ($revisionIds === []) {
      return $form;
    }
    sort($revisionIds, SORT_NUMERIC);
    $olderDefault = count($revisionIds) === 1 && $olderRevisionId !== NULL
      ? $olderRevisionId
      : $revisionIds[0];
    $newerDefault = end($revisionIds);
    $form['revision_ids_help'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Os IDs aparecem na tabela do histórico. Você pode informar revisões de páginas diferentes.'),
    ];
    $form['older_revision'] = [
      '#type' => 'number',
      '#title' => $this->t('Revisão mais antiga'),
      '#default_value' => $olderDefault,
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['newer_revision'] = [
      '#type' => 'number',
      '#title' => $this->t('Revisão mais recente'),
      '#default_value' => $newerDefault,
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'compare' => [
        '#type' => 'submit',
        '#value' => $this->t('Comparar revisões selecionadas'),
        '#button_type' => 'primary',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $older = filter_var($form_state->getValue('older_revision'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newer = filter_var($form_state->getValue('newer_revision'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($older === FALSE) {
      $form_state->setErrorByName('older_revision', $this->t('Informe um ID numérico de revisão válido.'));
    }
    if ($newer === FALSE) {
      $form_state->setErrorByName('newer_revision', $this->t('Informe um ID numérico de revisão válido.'));
    }
    if ($older === FALSE || $newer === FALSE) {
      return;
    }
    if ($older === $newer) {
      $form_state->setErrorByName('newer_revision', $this->t('Selecione duas revisões diferentes desta publicação.'));
      return;
    }

    $post = $this->forumRouteMatch->getParameter('aculta_forum_post');
    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    if (!$post instanceof ForumPost || !$storage instanceof RevisionableStorageInterface) {
      $form_state->setErrorByName('newer_revision', $this->t('Não foi possível identificar a publicação.'));
      return;
    }
    foreach (['older_revision' => $older, 'newer_revision' => $newer] as $element => $revisionId) {
      $revision = $storage->loadRevision($revisionId);
      if (!$revision instanceof ForumPost || (string) $revision->id() !== (string) $post->id()) {
        $form_state->setErrorByName($element, $this->t('Cada revisão precisa pertencer a esta publicação.'));
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $post = $this->forumRouteMatch->getParameter('aculta_forum_post');
    if (!$post instanceof ForumPost) {
      $form_state->setErrorByName('newer_revision', $this->t('Não foi possível identificar a publicação.'));
      return;
    }
    $older = filter_var($form_state->getValue('older_revision'), FILTER_VALIDATE_INT);
    $newer = filter_var($form_state->getValue('newer_revision'), FILTER_VALIDATE_INT);
    if ($older === FALSE || $newer === FALSE) {
      $form_state->setErrorByName('newer_revision', $this->t('Não foi possível identificar as revisões.'));
      return;
    }
    $revisionIds = [(int) $older, (int) $newer];
    sort($revisionIds, SORT_NUMERIC);
    $form_state->setRedirectUrl(Url::fromRoute('aculta_forum.post_revision_compare', [
      'aculta_forum_post' => $post->id(),
      'left_revision' => $revisionIds[0],
      'right_revision' => $revisionIds[1],
    ]));
  }

}
