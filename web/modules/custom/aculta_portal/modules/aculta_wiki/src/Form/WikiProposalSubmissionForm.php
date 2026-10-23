<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Form;

use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Service\WikiProposalSubmission;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Form API for a private contribution proposal on a published Wiki entry.
 */
final class WikiProposalSubmissionForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WikiProposalSubmission $submission,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_wiki.proposal_submission'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_wiki_proposal_submission';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $this->submission->assertCanSubmit();
    if (!$node instanceof NodeInterface) {
      throw new \InvalidArgumentException('A Wiki entry is required to submit a proposal.');
    }
    $form_state->set('wiki_proposal_target_id', (int) $node->id());
    $submissionToken = $form_state->get('wiki_proposal_submission_token');
    if (!is_string($submissionToken)) {
      $submissionToken = bin2hex(random_bytes(32));
      $form_state->set('wiki_proposal_submission_token', $submissionToken);
    }

    $form['intro'] = [
      '#type' => 'item',
      '#title' => $this->t('Proposta para: @title', ['@title' => $node->label()]),
      '#markup' => $this->t('Sua sugestão será enviada à equipe editorial. Ela não altera o verbete automaticamente.'),
    ];
    $form['intent'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de contribuição'),
      '#options' => WikiProposal::INTENTS,
      '#required' => TRUE,
    ];
    $form['proposed_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Alteração sugerida'),
      '#description' => $this->t('Descreva o trecho ou a alteração que você recomenda, em texto simples.'),
      '#required' => TRUE,
      '#rows' => 8,
      '#maxlength' => 20000,
    ];
    $form['rationale'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Por que essa mudança melhora o verbete?'),
      '#required' => TRUE,
      '#rows' => 4,
      '#maxlength' => 5000,
    ];
    $form['source_uris'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fontes'),
      '#description' => $this->t('Opcional. Uma URL HTTP ou HTTPS por linha, até dez fontes.'),
      '#rows' => 4,
      '#maxlength' => 20480,
    ];
    $form['submission_token'] = [
      '#type' => 'hidden',
      '#value' => $submissionToken,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar proposta'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $text = trim((string) $form_state->getValue('proposed_text'));
    $rationale = trim((string) $form_state->getValue('rationale'));
    if ($text === '' || mb_strlen($text) > 20000) {
      $form_state->setErrorByName('proposed_text', $this->t('Informe uma sugestão de até 20.000 caracteres.'));
    }
    if ($rationale === '' || mb_strlen($rationale) > 5000) {
      $form_state->setErrorByName('rationale', $this->t('Informe uma justificativa de até 5.000 caracteres.'));
    }
    $sources = $this->getSourceUris((string) $form_state->getValue('source_uris'));
    if (count($sources) > 10) {
      $form_state->setErrorByName('source_uris', $this->t('Informe no máximo dez URLs, uma por linha.'));
    }
    foreach ($sources as $source) {
      $scheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));
      if (!filter_var($source, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], TRUE)) {
        $form_state->setErrorByName('source_uris', $this->t('Cada fonte precisa ser uma URL HTTP ou HTTPS válida.'));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->entityTypeManager->getStorage('node')->load((int) $form_state->get('wiki_proposal_target_id'));
    if (!$node instanceof NodeInterface) {
      throw new AccessDeniedHttpException();
    }
    try {
      $proposal = $this->submission->submit(
        $node,
        (string) $form_state->getValue('intent'),
        (string) $form_state->getValue('proposed_text'),
        (string) $form_state->getValue('rationale'),
        $this->getSourceUris((string) $form_state->getValue('source_uris')),
        (string) $form_state->getValue('submission_token'),
      );
    }
    catch (UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('proposed_text', $exception->getMessage());
      return;
    }

    $this->messenger()->addStatus($this->t('Proposta enviada para avaliação editorial.'));
    $form_state->setRedirect('aculta_wiki.proposal_status', ['proposal' => $proposal->id()]);
  }

  /**
   * Splits the optional one-URL-per-line field.
   *
   * @return list<string>
   *   Non-empty source URIs.
   */
  private function getSourceUris(string $value): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $value) ?: []), static fn (string $uri): bool => $uri !== ''));
  }

}
