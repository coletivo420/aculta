<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Form;

use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Service\WikiProposalDecision;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Form API for recording an editorial response to a Wiki proposal.
 */
final class WikiProposalDecisionForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly WikiProposalDecision $decision,
    protected readonly ContributionLifecycle $contributions,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly DomainSiteManager $siteManagers,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_wiki.proposal_decision'),
      $container->get('aculta_cgu.contribution_lifecycle'),
      $container->get('module_handler'),
      $container->get('aculta_portal.domain_site'),
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
  public function buildForm(array $form, FormStateInterface $form_state, WikiProposal|Contribution|null $proposal = NULL): array {
    if ((!$proposal instanceof WikiProposal && !$proposal instanceof Contribution) || !$proposal->access('update')) {
      throw new AccessDeniedHttpException();
    }
    if (!in_array($proposal->get('state')->value, ['submitted', 'needs_changes'], TRUE)) {
      throw new ConflictHttpException('Esta proposta já recebeu uma decisão final.');
    }
    $form_state->set('wiki_proposal_id', (int) $proposal->id());
    $isContribution = $proposal instanceof Contribution;
    $sourceData = $isContribution ? $proposal->getSourceData() : [];
    $proposedFields = $isContribution ? $proposal->getPayload() : $proposal->getProposedFields();
    $form_state->set('wiki_proposal_fields', array_keys($proposedFields));

    $form['#attributes']['data-portal-ui'] = 'wiki-proposal-decision';
    $form['proposal_header'] = [
      '#theme' => 'aculta_portal_component',
      '#name' => 'context-header',
      '#props' => [
        'eyebrow' => (string) $this->t('ACULTA Studio Marley · Moderação Wiki'),
        'title' => (string) $this->t('Proposta: @title', ['@title' => $proposal->label()]),
        'subtitle' => (string) $this->t('Revise a contribuição e registre uma decisão editorial.'),
      ],
    ];
    $contributionBody = [
      '#type' => 'container',
      'intro' => ['#plain_text' => $this->t('O retorno será enviado à pessoa autora e registrado no histórico privado da proposta.')],
    ];
    $form['editorial_decision'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Decisão editorial'),
    ];
    $target = $proposal instanceof WikiProposal
      ? $proposal->get('target')->entity
      : $this->loadWikiTarget($proposal);
    if ($target !== NULL) {
      $targetUrl = $this->siteManagers->routeUrl(SiteKey::Wiki, 'entity.node.canonical', ['node' => $target->id()]);
      if ($targetUrl !== NULL && $this->siteManagers->runInSite(SiteKey::Wiki, static fn (): bool => $targetUrl->access())) {
        $form['proposal_header']['#slots']['actions']['target'] = [
          '#type' => 'link',
          '#title' => $this->t('Abrir verbete publicado'),
          '#url' => $targetUrl,
        ];
      }
      $revisionUrl = $this->siteManagers->routeUrl(SiteKey::Wiki, 'entity.node.revision', [
        'node' => $target->id(),
        'node_revision' => $proposal instanceof WikiProposal
          ? $proposal->get('target_revision_id')->value
          : $proposal->get('base_revision')->value,
      ]);
      if ($revisionUrl !== NULL && $this->siteManagers->runInSite(SiteKey::Wiki, static fn (): bool => $revisionUrl->access())) {
        $form['proposal_header']['#slots']['actions']['base_revision'] = [
          '#type' => 'link',
          '#title' => $this->t('Abrir revisão-base da proposta'),
          '#url' => $revisionUrl,
        ];
      }
    }
    $proposedText = $proposal instanceof Contribution
      ? (string) ($sourceData['proposed_text'] ?? '')
      : (string) $proposal->get('proposed_text')->value;
    $contributionBody['proposed_text'] = [
      '#type' => 'container',
      'label' => [
        '#plain_text' => $this->t('Alteração sugerida'),
      ],
      'text' => [
        '#plain_text' => $proposedText,
      ],
    ];
    if ($proposedFields !== []) {
      $contributionBody['proposed_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Valores propostos'),
        '#open' => TRUE,
      ];
      foreach ($proposedFields as $fieldName => $value) {
        if (!isset(WikiProposal::EDITORIAL_FIELDS[$fieldName])) {
          continue;
        }
        $contributionBody['proposed_fields'][$fieldName] = [
          '#type' => 'item',
          '#title' => WikiProposal::EDITORIAL_FIELDS[$fieldName],
          '#markup' => '<pre>' . htmlspecialchars($this->formatProposedValue($fieldName, $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>',
        ];
      }
      $form['editorial_decision']['approved_fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Campos aprovados para aplicação'),
        '#options' => array_intersect_key(WikiProposal::EDITORIAL_FIELDS, $proposedFields),
        '#description' => $this->t('Escolha o subconjunto que será aplicado ao verbete.'),
        '#states' => ['visible' => [':input[name="decision"]' => ['value' => 'accepted']]],
      ];
      $form['editorial_decision']['list_modes'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
      foreach (['field_wiki_references', 'field_wiki_categories', 'field_wiki_related'] as $fieldName) {
        if (!array_key_exists($fieldName, $proposedFields)) {
          continue;
        }
        $form['editorial_decision']['list_modes'][$fieldName] = [
          '#type' => 'select',
          '#title' => $this->t('@field: como aplicar', ['@field' => WikiProposal::EDITORIAL_FIELDS[$fieldName]]),
          '#options' => [
            'replace' => $this->t('Substituir a lista atual'),
            'append' => $this->t('Acrescentar itens à lista atual'),
          ],
          '#empty_option' => $this->t('- Escolha como aplicar -'),
          '#empty_value' => '',
          '#default_value' => '',
          '#states' => [
            'visible' => [
              ':input[name="decision"]' => ['value' => 'accepted'],
              ':input[name="approved_fields[' . $fieldName . ']"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }
    $contributionBody['rationale'] = [
      '#type' => 'container',
      'label' => ['#plain_text' => $this->t('Justificativa')],
      'text' => ['#plain_text' => (string) $proposal->get('rationale')->value],
    ];
    $sourceLinks = [];
    $sourceUris = $proposal instanceof Contribution
      ? (array) ($sourceData['source_uris'] ?? [])
      : array_map(static fn ($item): string => (string) ($item->getValue()['value'] ?? ''), iterator_to_array($proposal->get('source_uri')));
    foreach ($sourceUris as $sourceUri) {
      $uri = is_string($sourceUri) ? $sourceUri : '';
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
    $contributionBody['sources'] = $sourceLinks === [] ? [] : [
      '#theme' => 'item_list',
      '#title' => $this->t('Fontes apresentadas'),
      '#items' => $sourceLinks,
    ];
    $form['contribution'] = [
      '#theme' => 'aculta_portal_component',
      '#name' => 'content-section',
      '#props' => ['title' => (string) $this->t('Contribuição recebida')],
      '#slots' => ['body' => $contributionBody],
    ];
    $form['expected_revision_id'] = [
      '#type' => 'value',
      '#value' => (int) $proposal->getRevisionId(),
    ];
    $form['editorial_decision']['decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Decisão'),
      '#options' => [
        'needs_changes' => $this->t('Pedir ajustes'),
        'declined' => $this->t('Recusar'),
        'accepted' => $this->t('Aceitar e aplicar campos'),
      ],
      '#required' => TRUE,
    ];
    $form['editorial_decision']['decision_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Retorno para a pessoa autora'),
      '#description' => $this->t('Registre o retorno editorial para a pessoa autora. Texto simples, até 5.000 caracteres.'),
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
    if ($form_state->getValue('decision') === 'accepted') {
      $approved = array_values(array_filter((array) $form_state->getValue('approved_fields')));
      $available = (array) $form_state->get('wiki_proposal_fields');
      if ($approved === [] || array_diff($approved, $available) !== []) {
        $form_state->setErrorByName('approved_fields', $this->t('Selecione ao menos um campo proposto para aplicar.'));
      }
      $listModes = (array) $form_state->getValue('list_modes');
      foreach (['field_wiki_references', 'field_wiki_categories', 'field_wiki_related'] as $fieldName) {
        if (in_array($fieldName, $approved, TRUE)
          && !in_array($listModes[$fieldName] ?? NULL, ['replace', 'append'], TRUE)) {
          $form_state->setErrorByName('list_modes[' . $fieldName . ']', $this->t('Escolha substituir ou acrescentar @field.', [
            '@field' => WikiProposal::EDITORIAL_FIELDS[$fieldName],
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) $form_state->get('wiki_proposal_id');
    $proposal = $this->entityTypeManager->getStorage('aculta_contribution')->load($id);
    if (!$proposal instanceof Contribution || $proposal->get('source')->value !== 'wiki') {
      $proposal = $this->entityTypeManager->getStorage('aculta_wiki_proposal')->load($id);
    }
    if (!$proposal instanceof WikiProposal && !$proposal instanceof Contribution) {
      throw new AccessDeniedHttpException();
    }
    try {
      $selectedFields = array_values(array_filter((array) $form_state->getValue('approved_fields')));
      $listModes = (array) $form_state->getValue('list_modes');
      if ($proposal instanceof Contribution) {
        $decisionData = $form_state->getValue('decision') === 'accepted'
          ? ['list_modes' => $listModes]
          : [];
        $this->contributions->decide(
          $proposal,
          (string) $form_state->getValue('decision'),
          (string) $form_state->getValue('decision_note'),
          $selectedFields,
          (int) $form_state->getValue('expected_revision_id'),
          $decisionData,
        );
      }
      else {
        $this->decision->decide(
          $proposal,
          (int) $form_state->getValue('expected_revision_id'),
          (string) $form_state->getValue('decision'),
          (string) $form_state->getValue('decision_note'),
          $selectedFields,
          $listModes,
        );
      }
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

  /**
   * Loads the target node from a generic Wiki contribution's stable UUID.
   */
  private function loadWikiTarget(Contribution $proposal): ?NodeInterface {
    $uuid = (string) $proposal->get('target_uuid')->value;
    if ($uuid === '') {
      return NULL;
    }
    $matches = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'uuid' => $uuid,
      'type' => 'wiki_entry',
    ]);
    $target = reset($matches);
    return $target instanceof NodeInterface ? $target : NULL;
  }

  /**
   * Formats proposal values as escaped plain text for editorial review.
   */
  private function formatProposedValue(string $fieldName, mixed $value): string {
    if (in_array($fieldName, ['field_wiki_summary', 'body'], TRUE)) {
      return (string) ($value['value'] ?? '');
    }
    if ($fieldName === 'field_wiki_references') {
      return implode("\n", array_map(static fn (array $item): string => trim(($item['title'] ?? '') . ' | ' . ($item['uri'] ?? ''), ' |'), (array) $value));
    }
    if (in_array($fieldName, ['field_wiki_categories', 'field_wiki_related', 'field_wiki_image'], TRUE)) {
      $type = match ($fieldName) {
        'field_wiki_categories' => 'taxonomy_term',
        'field_wiki_related' => 'node',
        default => 'media',
      };
      $storage = $this->entityTypeManager->getStorage($type);
      $ids = $fieldName === 'field_wiki_image' ? [$value] : (array) $value;
      $labels = [];
      foreach ($ids as $id) {
        $entity = is_numeric($id) ? $storage->load((int) $id) : NULL;
        $labels[] = $entity !== NULL && $entity->access('view') ? (string) $entity->label() : (string) $this->t('[indisponível]');
      }
      return implode("\n", $labels);
    }
    if (is_array($value)) {
      return (string) ($value['value'] ?? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return (string) $value;
  }

}
