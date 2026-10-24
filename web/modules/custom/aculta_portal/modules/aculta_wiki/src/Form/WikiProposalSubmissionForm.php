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
    $form['field_values'] = ['#type' => 'container'];
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
      $proposedFields = $this->getProposedFields($form_state);
      $proposal = $this->submission->submit(
        $node,
        (string) $form_state->getValue('intent'),
        (string) $form_state->getValue('proposed_text'),
        (string) $form_state->getValue('rationale'),
        $this->getSourceUris((string) $form_state->getValue('source_uris')),
        (string) $form_state->getValue('submission_token'),
        $proposedFields,
      );
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
   * Extracts only the fields explicitly selected by the contributor.
   *
   * @return array<string, mixed>
   *   Structured allowlisted field values.
   */
  private function getProposedFields(FormStateInterface $form_state): array {
    $selected = array_filter((array) $form_state->getValue('proposed_fields_selected'));
    $values = (array) $form_state->getValue('field_values');
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
