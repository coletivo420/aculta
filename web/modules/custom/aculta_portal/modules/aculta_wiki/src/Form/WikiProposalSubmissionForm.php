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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
    $node = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    if (!$node instanceof NodeInterface || $node->bundle() !== 'wiki_entry' || !$node->isDefaultRevision() || !$node->isPublished() || !$node->access('view') || $node->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('wiki_proposal_target_id', (int) $node->id());
    if (!$form_state->has('wiki_proposal_expected_revision')) {
      $form_state->set('wiki_proposal_expected_revision', (int) $node->getRevisionId());
    }
    $step = $form_state->get('wiki_proposal_step') ?? 'edit';
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
    if ($form_state->get('wiki_proposal_base_changed')) {
      $form['base_changed'] = [
        '#type' => 'container',
        '#attributes' => ['role' => 'alert'],
        'message' => ['#plain_text' => $this->t('O verbete recebeu uma nova versão enquanto você preparava a proposta. Revise seu conteúdo antes de continuar.')],
      ];
      $form['confirm_current_base'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Revisei a proposta considerando a versão atual do verbete.'),
        '#required' => TRUE,
      ];
    }
    $form['intent'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de contribuição'),
      '#options' => WikiProposal::INTENTS,
      '#required' => TRUE,
    ];
    $requestedIntent = $this->getRequest()?->query->all()['intent'] ?? NULL;
    if (is_string($requestedIntent) && isset(WikiProposal::INTENTS[$requestedIntent])) {
      $form['intent']['#default_value'] = $requestedIntent;
    }
    $form['proposed_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Resumo da contribuição'),
      '#description' => $this->t('Explique brevemente o que deve mudar. Selecione abaixo os campos e valores propostos.'),
      '#required' => TRUE,
      '#rows' => 8,
      '#maxlength' => 20000,
    ];
    $form['proposed_fields_selected'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Campos que proponho alterar'),
      '#options' => [
        'title' => $this->t('Título'),
        'field_wiki_summary' => $this->t('Resumo'),
        'body' => $this->t('Corpo'),
        'field_wiki_references' => $this->t('Referências'),
        'field_wiki_categories' => $this->t('Categorias'),
        'field_wiki_related' => $this->t('Ver também'),
        'field_wiki_image' => $this->t('Imagem'),
      ],
      '#required' => TRUE,
    ];
    $form['field_values'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['field_values']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Novo título'),
      '#maxlength' => 255,
      '#states' => ['visible' => [':input[name="proposed_fields_selected[title]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['summary'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Novo resumo'),
      '#allowed_formats' => ['wiki', 'plain_text'],
      '#states' => ['visible' => [':input[name="proposed_fields_selected[field_wiki_summary]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Novo corpo do verbete'),
      '#allowed_formats' => ['wiki', 'plain_text'],
      '#states' => ['visible' => [':input[name="proposed_fields_selected[body]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['body_summary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resumo do corpo (opcional)'),
      '#maxlength' => 512,
      '#states' => ['visible' => [':input[name="proposed_fields_selected[body]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['references'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Referências propostas'),
      '#description' => $this->t('Uma por linha: URL | título. Ao aprovar, a equipe pode substituir ou acrescentar à lista atual.'),
      '#states' => ['visible' => [':input[name="proposed_fields_selected[field_wiki_references]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['categories'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Categorias propostas'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => ['target_bundles' => ['wiki_category']],
      '#tags' => TRUE,
      '#states' => ['visible' => [':input[name="proposed_fields_selected[field_wiki_categories]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['related'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Verbetes relacionados propostos'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['wiki_entry' => 'wiki_entry']],
      '#tags' => TRUE,
      '#states' => ['visible' => [':input[name="proposed_fields_selected[field_wiki_related]"]' => ['checked' => TRUE]]],
    ];
    $form['field_values']['image'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Imagem proposta'),
      '#description' => $this->t('Escolha uma imagem existente e publicada. O acesso será validado novamente ao enviar e ao aplicar.'),
      '#target_type' => 'media',
      '#selection_settings' => ['target_bundles' => ['image' => 'image']],
      '#states' => ['visible' => [':input[name="proposed_fields_selected[field_wiki_image]"]' => ['checked' => TRUE]]],
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
    $editableElements = [
      'intent', 'proposed_text', 'proposed_fields_selected', 'field_values',
      'rationale', 'source_uris', 'submission_token',
    ];
    if ($step === 'review') {
      foreach ($editableElements as $element) {
        $form[$element]['#access'] = FALSE;
      }
      $form['review'] = $this->buildReview($form_state);
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Voltar e ajustar'),
        '#submit' => ['::backToEdit'],
        '#limit_validation_errors' => [],
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Enviar para revisão'),
      ];
    }
    else {
      $form['actions']['continue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Revisar e enviar'),
        '#submit' => ['::continueToReview'],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->get('wiki_proposal_step') === 'review') {
      return;
    }
    if ($form_state->get('wiki_proposal_base_changed') && !$form_state->getValue('confirm_current_base')) {
      $form_state->setErrorByName('confirm_current_base', $this->t('Confirme que revisou a proposta para a versão atual do verbete.'));
    }
    $text = trim((string) $form_state->getValue('proposed_text'));
    $rationale = trim((string) $form_state->getValue('rationale'));
    if ($text === '' || mb_strlen($text) > 20000) {
      $form_state->setErrorByName('proposed_text', $this->t('Informe uma sugestão de até 20.000 caracteres.'));
    }
    if ($rationale === '' || mb_strlen($rationale) > 5000) {
      $form_state->setErrorByName('rationale', $this->t('Informe uma justificativa de até 5.000 caracteres.'));
    }
    $selected = array_filter((array) $form_state->getValue('proposed_fields_selected'));
    if ($selected === []) {
      $form_state->setErrorByName('proposed_fields_selected', $this->t('Selecione ao menos um campo para propor.'));
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
      $values = $form_state->get('wiki_proposal_review_values');
      if (!is_array($values)) {
        $values = $form_state->getValues();
      }
      $proposedFields = $this->extractProposedFields($values);
      $proposal = $this->submission->submit(
        $node,
        (string) ($values['intent'] ?? ''),
        (string) ($values['proposed_text'] ?? ''),
        (string) ($values['rationale'] ?? ''),
        $this->getSourceUris((string) ($values['source_uris'] ?? '')),
        (string) ($values['submission_token'] ?? $form_state->get('wiki_proposal_submission_token')),
        $proposedFields,
        (int) $form_state->get('wiki_proposal_expected_revision'),
      );
    }
    catch (ConflictHttpException $exception) {
      $form_state->set('wiki_proposal_conflict', TRUE);
      $form_state->setErrorByName('', $exception->getMessage());
      return;
    }
    catch (UnprocessableEntityHttpException $exception) {
      // Backend revalidation may reject any selected editorial field. Keep
      // that message at form level instead of incorrectly marking the summary
      // textarea as invalid.
      $form_state->setErrorByName('', $exception->getMessage());
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

  /**
   * Returns selected fields from a specific Form API value set.
   *
   * @param array<string, mixed> $submittedValues
   *   Current or server-stored form values.
   *
   * @return array<string, mixed>
   *   Structured allowlisted field values.
   */
  private function extractProposedFields(array $submittedValues): array {
    $selected = array_filter((array) ($submittedValues['proposed_fields_selected'] ?? []));
    $values = (array) ($submittedValues['field_values'] ?? []);
    $fields = [];
    foreach ($selected as $fieldName => $selectedValue) {
      if ($selectedValue === 0 || $selectedValue === '0' || $selectedValue === '') {
        continue;
      }
      switch ($fieldName) {
        case 'title':
          $fields[$fieldName] = (string) ($values['title'] ?? '');
          break;

        case 'field_wiki_summary':
          $fields[$fieldName] = (array) ($values['summary'] ?? []);
          break;

        case 'body':
          $fields[$fieldName] = (array) ($values['body'] ?? []) + [
            'summary' => (string) ($values['body_summary'] ?? ''),
          ];
          break;

        case 'field_wiki_references':
          $references = [];
          foreach (array_filter(array_map('trim', preg_split('/\R/', (string) ($values['references'] ?? '')) ?: [])) as $line) {
            [$uri, $title] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $references[] = ['uri' => $uri, 'title' => $title];
          }
          $fields[$fieldName] = $references;
          break;

        case 'field_wiki_categories':
          $fields[$fieldName] = $this->extractTargetIds($values['categories'] ?? []);
          break;

        case 'field_wiki_related':
          $fields[$fieldName] = $this->extractTargetIds($values['related'] ?? []);
          break;

        case 'field_wiki_image':
          $fields[$fieldName] = $this->extractTargetId($values['image'] ?? NULL);
          break;
      }
    }
    return $fields;
  }

  /**
   * Saves validated values in Form API storage and advances to review.
   */
  public function continueToReview(array &$form, FormStateInterface $form_state): void {
    $form_state->set('wiki_proposal_review_values', $form_state->getValues());
    $form_state->set('wiki_proposal_step', 'review');
    $form_state->set('wiki_proposal_base_changed', FALSE);
    $form_state->setRebuild();
  }

  /**
   * Restores server-stored values and returns to the editable step.
   */
  public function backToEdit(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->get('wiki_proposal_review_values');
    if (is_array($values)) {
      $form_state->setValues($values);
    }
    if ($form_state->get('wiki_proposal_conflict')) {
      $targetId = (int) $form_state->get('wiki_proposal_target_id');
      $current = $this->entityTypeManager->getStorage('node')->loadUnchanged($targetId);
      if (!$current instanceof NodeInterface || !$current->isDefaultRevision() || !$current->isPublished() || !$current->access('view') || $current->access('update')) {
        throw new AccessDeniedHttpException();
      }
      $form_state->set('wiki_proposal_expected_revision', (int) $current->getRevisionId());
      $form_state->set('wiki_proposal_base_changed', TRUE);
      $form_state->set('wiki_proposal_conflict', FALSE);
    }
    $form_state->set('wiki_proposal_step', 'edit');
    $form_state->setRebuild();
  }

  /**
   * Builds a plain-text review summary from values held in Form API storage.
   */
  private function buildReview(FormStateInterface $form_state): array {
    $values = $form_state->get('wiki_proposal_review_values');
    $values = is_array($values) ? $values : [];
    $fields = $this->extractProposedFields($values);
    $fieldSummaries = [];
    foreach ($fields as $fieldName => $value) {
      $fieldSummaries[] = (string) WikiProposal::EDITORIAL_FIELDS[$fieldName] . ': ' . $this->formatProposedValue($fieldName, $value);
    }
    $sourceUris = $this->getSourceUris((string) ($values['source_uris'] ?? ''));
    $items = [
      '#type' => 'component',
      '#component' => 'aculta420:content-section',
      '#props' => ['title' => (string) $this->t('Revise antes de enviar')],
      '#slots' => [
        'body' => [
          'intent' => ['#plain_text' => $this->t('Tipo: @intent', ['@intent' => WikiProposal::INTENTS[$values['intent'] ?? ''] ?? ''])],
          'summary' => ['#plain_text' => (string) ($values['proposed_text'] ?? '')],
          'fields' => ['#plain_text' => $this->t('Valores propostos: @fields', ['@fields' => implode("\n", $fieldSummaries)])],
          'rationale' => ['#plain_text' => $this->t('Justificativa: @rationale', ['@rationale' => (string) ($values['rationale'] ?? '')])],
          'sources' => ['#plain_text' => $sourceUris === [] ? $this->t('Nenhuma fonte adicionada.') : implode("\n", $sourceUris)],
          'notice' => ['#plain_text' => $this->t('A proposta será analisada pela equipe editorial e não será publicada automaticamente.')],
        ],
      ],
    ];
    if ($form_state->get('wiki_proposal_conflict')) {
      $items['#slots']['body']['conflict'] = [
        '#plain_text' => $this->t('O verbete mudou. Volte para revisar sua proposta antes de enviá-la novamente.'),
      ];
    }
    return $items;
  }

  /**
   * Formats allowlisted proposal values as escaped, readable review text.
   */
  private function formatProposedValue(string $fieldName, mixed $value): string {
    if (in_array($fieldName, ['field_wiki_summary', 'body'], TRUE)) {
      return trim(strip_tags((string) ($value['value'] ?? '')));
    }
    if ($fieldName === 'field_wiki_references') {
      return implode("\n", array_map(
        static fn (array $item): string => trim(($item['title'] ?? '') . ' | ' . ($item['uri'] ?? ''), ' |'),
        (array) $value,
      ));
    }
    if (in_array($fieldName, ['field_wiki_categories', 'field_wiki_related', 'field_wiki_image'], TRUE)) {
      $type = match ($fieldName) {
        'field_wiki_categories' => 'taxonomy_term',
        'field_wiki_related' => 'node',
        default => 'media',
      };
      $ids = $fieldName === 'field_wiki_image' ? [$value] : (array) $value;
      $labels = [];
      foreach ($ids as $id) {
        $entity = is_numeric($id) ? $this->entityTypeManager->getStorage($type)->load((int) $id) : NULL;
        $labels[] = $entity !== NULL && $entity->access('view')
          ? (string) $entity->label()
          : (string) $this->t('[indisponível]');
      }
      return implode(', ', $labels);
    }
    return (string) $value;
  }

  /**
   * Normalizes entity autocomplete output to a list of target IDs.
   *
   * @return list<int|string>
   *   IDs submitted by the autocomplete element.
   */
  private function extractTargetIds(mixed $value): array {
    if (!is_array($value)) {
      return is_string($value) && trim($value) !== '' ? [$value] : [];
    }
    $ids = [];
    foreach ($value as $item) {
      if (is_array($item) && isset($item['target_id'])) {
        $ids[] = $item['target_id'];
      }
      elseif (is_scalar($item)) {
        $ids[] = $item;
      }
    }
    return $ids;
  }

  /**
   * Extracts one ID from the media autocomplete element.
   */
  private function extractTargetId(mixed $value): int|string|null {
    if (is_array($value)) {
      $value = $value['target_id'] ?? NULL;
    }
    return is_numeric($value) ? (int) $value : NULL;
  }

}
