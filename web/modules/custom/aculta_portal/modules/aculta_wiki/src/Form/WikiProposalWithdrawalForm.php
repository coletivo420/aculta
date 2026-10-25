<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Service\WikiProposalWithdrawal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Confirms an author's request to withdraw a pending Wiki proposal.
 */
final class WikiProposalWithdrawalForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WikiProposalWithdrawal $withdrawal,
    MessengerInterface $messenger,
  ) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_wiki.proposal_withdrawal'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_wiki_proposal_withdrawal';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WikiProposal $proposal = NULL): array {
    if (!$proposal instanceof WikiProposal
      || (int) $proposal->getOwnerId() !== (int) $this->currentUser()->id()
      || !$proposal->access('view', $this->currentUser())
      || !in_array($proposal->get('state')->value, ['submitted', 'needs_changes'], TRUE)) {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('wiki_proposal_id', (int) $proposal->id());
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Retirar proposta: @title', ['@title' => $proposal->label()]),
      '#markup' => $this->t('A proposta será marcada como retirada e continuará disponível no seu histórico privado.'),
    ];
    $form['expected_revision_id'] = [
      '#type' => 'value',
      '#value' => (int) $proposal->getRevisionId(),
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo que quero retirar esta proposta.'),
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Retirar proposta'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar ao status da proposta'),
      '#url' => Url::fromRoute('aculta_wiki.proposal_status', ['proposal' => $proposal->id()]),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if (!$form_state->getValue('confirm')) {
      $form_state->setErrorByName('confirm', $this->t('Confirme a retirada da proposta.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $proposal = $this->entityTypeManager->getStorage('aculta_wiki_proposal')
      ->load((int) $form_state->get('wiki_proposal_id'));
    if (!$proposal instanceof WikiProposal) {
      throw new AccessDeniedHttpException();
    }
    try {
      $proposal = $this->withdrawal->withdraw($proposal, (int) $form_state->getValue('expected_revision_id'));
    }
    catch (ConflictHttpException $exception) {
      $form_state->setErrorByName('confirm', $exception->getMessage());
      return;
    }

    $this->messenger()->addStatus($this->t('A proposta foi retirada. Ela permanece no seu histórico privado.'));
    $form_state->setRedirect('aculta_wiki.proposal_status', ['proposal' => $proposal->id()]);
  }

}
