<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Form;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Service\WikiProposalDecision;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Form API for recording an editorial response to a Wiki proposal.
 */
final class WikiProposalDecisionForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WikiProposalDecision $decision,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly DomainPurposeManager $domainPurposes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_wiki.proposal_decision'),
      $container->get('module_handler'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_wiki_proposal_decision';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WikiProposal $proposal = NULL): array {
    if (!$proposal instanceof WikiProposal || !$proposal->access('update')) {
      throw new AccessDeniedHttpException();
    }
    if (!in_array($proposal->get('state')->value, ['submitted', 'needs_changes'], TRUE)) {
      throw new ConflictHttpException('Esta proposta já recebeu uma decisão final.');
    }
    $form_state->set('wiki_proposal_id', (int) $proposal->id());

    $form['proposal'] = [
      '#type' => 'item',
      '#title' => $this->t('Proposta: @title', ['@title' => $proposal->label()]),
      '#markup' => $this->t('O retorno será enviado à pessoa autora e registrado no histórico privado da proposta.'),
    ];
    $target = $proposal->get('target')->entity;
    if ($target !== NULL) {
      $targetUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.canonical', ['node' => $target->id()]);
      if ($targetUrl !== NULL && $this->domainPurposes->runInPurpose('wiki', static fn (): bool => $targetUrl->access())) {
        $form['target_link'] = [
          '#type' => 'link',
          '#title' => $this->t('Abrir verbete publicado'),
          '#url' => $targetUrl,
        ];
      }
      $revisionUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.revision', [
        'node' => $target->id(),
        'node_revision' => $proposal->get('target_revision_id')->value,
      ]);
      if ($revisionUrl !== NULL && $this->domainPurposes->runInPurpose('wiki', static fn (): bool => $revisionUrl->access())) {
        $form['base_revision_link'] = [
          '#type' => 'link',
          '#title' => $this->t('Abrir revisão-base da proposta'),
          '#url' => $revisionUrl,
        ];
      }
    }
    $form['proposed_text'] = [
      '#type' => 'container',
      'label' => ['#plain_text' => $this->t('Alteração sugerida')],
      'text' => ['#plain_text' => (string) $proposal->get('proposed_text')->value],
    ];
    $form['rationale'] = [
      '#type' => 'container',
      'label' => ['#plain_text' => $this->t('Justificativa')],
      'text' => ['#plain_text' => (string) $proposal->get('rationale')->value],
    ];
    $sourceLinks = [];
    foreach ($proposal->get('source_uri') as $sourceItem) {
      $uri = (string) $sourceItem->value;
      if (!filter_var($uri, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($uri, PHP_URL_SCHEME)), ['http', 'https'], TRUE)) {
        continue;
      }
      $sourceLinks[] = [
        '#type' => 'link',
        '#title' => $uri,
        '#url' => Url::fromUri($uri),
        '#attributes' => ['rel' => 'noopener noreferrer'],
      ];
    }
    $form['sources'] = $sourceLinks === [] ? [] : [
      '#theme' => 'item_list',
      '#title' => $this->t('Fontes apresentadas'),
      '#items' => $sourceLinks,
    ];
    $form['expected_revision_id'] = [
      '#type' => 'value',
      '#value' => (int) $proposal->getRevisionId(),
    ];
    $form['decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Decisão'),
      '#options' => [
        'needs_changes' => $this->t('Pedir ajustes'),
        'declined' => $this->t('Recusar'),
      ],
      '#required' => TRUE,
    ];
    $form['decision_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Retorno para a pessoa autora'),
      '#description' => $this->t('Explique o que precisa mudar ou o motivo da recusa. Texto simples, até 5.000 caracteres.'),
      '#required' => TRUE,
      '#maxlength' => 5000,
      '#rows' => 5,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Registrar decisão'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $note = trim((string) $form_state->getValue('decision_note'));
    if ($note === '' || mb_strlen($note) > 5000) {
      $form_state->setErrorByName('decision_note', $this->t('Informe um retorno de até 5.000 caracteres.'));
    }
    if (!in_array($form_state->getValue('decision'), WikiProposalDecision::DECISIONS, TRUE)) {
      $form_state->setErrorByName('decision', $this->t('Escolha uma decisão disponível.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    $proposal = $storage->load((int) $form_state->get('wiki_proposal_id'));
    if (!$proposal instanceof WikiProposal) {
      throw new AccessDeniedHttpException();
    }
    try {
      $this->decision->decide(
        $proposal,
        (int) $form_state->getValue('expected_revision_id'),
        (string) $form_state->getValue('decision'),
        (string) $form_state->getValue('decision_note'),
      );
    }
    catch (ConflictHttpException $exception) {
      $form_state->setErrorByName('decision', $exception->getMessage());
      return;
    }
    catch (UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('decision_note', $exception->getMessage());
      return;
    }

    $this->messenger()->addStatus($this->t('A decisão foi registrada no histórico da proposta.'));
    if ($this->moduleHandler->moduleExists('aculta_studio')) {
      $form_state->setRedirect('aculta_studio.tab', [
        'aba' => 'wiki',
        'secao' => 'moderacao',
        'sub' => 'propostas',
      ]);
    }
  }

}
