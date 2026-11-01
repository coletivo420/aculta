<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Form;

use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Form API adapter for valid transitions of the Revista workflow. */
final class EditorialTransitionForm extends FormBase {

  public function __construct(private readonly EditorialCapability $capabilities, private readonly StateTransitionValidationInterface $transitions, private readonly AccountInterface $account) {}

  /**
   * Creates the transition form with the workflow and capability services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_magazine.capability'), $container->get('content_moderation.state_transition_validation'), $container->get('current_user'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_magazine_editorial_transition';
  }

  /**
   * Builds options from transitions valid for this exact revision and account.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node || !$this->allowed($node)) {
      throw new AccessDeniedHttpException();
    }
    $latest = $this->latest($node);
    if (!$latest->access('update', $this->account) || !$latest->access('view revision', $this->account)) {
      throw new AccessDeniedHttpException();
    }
    $options = [];
    foreach ($this->transitions->getValidTransitions($latest, $this->account) as $transition) {
      $options[$transition->id()] = $transition->label();
    }
    if ($options === []) {
      throw new AccessDeniedHttpException();
    }
    $form['node_id'] = ['#type' => 'value', '#value' => $node->id()];
    $form['state'] = [
      '#type' => 'select',
      '#title' => $this->t('Próxima etapa'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Aplicar transição')];
    return $form;
  }

  /**
   * Revalidates the transition and saves it through the moderated Node entity.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager()->getStorage('node');
    $node = $storage->load((int) $form_state->getValue('node_id'));
    if (!$node instanceof NodeInterface || !$this->allowed($node)) {
      throw new AccessDeniedHttpException();
    }
    $latestId = $storage->getLatestRevisionId($node->id());
    $latest = $latestId ? $storage->loadRevision($latestId) : NULL;
    if (!$latest instanceof NodeInterface || !$this->allowed($node) || !$latest->access('update', $this->account) || !$latest->access('view revision', $this->account)) {
      throw new AccessDeniedHttpException();
    }
    $valid = $this->transitions->getValidTransitions($latest, $this->account);
    $transitionId = (string) $form_state->getValue('state');
    $transition = NULL;
    foreach ($valid as $candidate) {
      if ($candidate->id() === $transitionId) {
        $transition = $candidate;
        break;
      }
    }
    if ($transition === NULL) {
      throw new AccessDeniedHttpException();
    }
    $latest->setNewRevision(TRUE);
    $latest->setRevisionUserId((int) $this->account->id());
    $latest->setRevisionLogMessage((string) $this->t('Transição editorial aplicada pelo ACULTA Studio Marley.'));
    $latest->set('moderation_state', $transition->to()->id());
    $latest->save();
    $this->messenger()->addStatus($this->t('A publicação foi atualizada para “@state”.', ['@state' => $transition->to()->label()]));
    $form_state->setRedirect('aculta_magazine.studio_list');
  }

  /**
   * Checks bundle, configured review role, and native Node/revision access.
   */
  private function allowed(NodeInterface $node): bool {
    return $node->bundle() === 'revista'
      && $this->capabilities->access(EditorialCapability::REVIEW, $this->account)->isAllowed()
      && $node->access('update', $this->account)
      && $node->access('view revision', $this->account);
  }

  /**
   * Loads the latest revision for transition discovery.
   */
  private function latest(NodeInterface $node): NodeInterface {
    $id = $this->entityTypeManager()->getStorage('node')->getLatestRevisionId($node->id());
    $latest = $id ? $this->entityTypeManager()->getStorage('node')->loadRevision($id) : NULL;
    return $latest instanceof NodeInterface ? $latest : $node;
  }

}
